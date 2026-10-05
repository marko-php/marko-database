<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Exceptions\ConstraintViolationHttp;

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use PDOException;

function driverError(
    string $sqlState,
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: Integrity constraint violation");
    $exception->errorInfo = [$sqlState, 7, 'ERROR:  violation'];

    return $exception;
}

/**
 * A controller whose actions fail the way a repository save() or delete()
 * does when the database rejects the write.
 */
class SignupController
{
    public function duplicateEmail(): Response
    {
        throw UniqueConstraintViolationException::fromDriverError(
            previous: driverError('23505'),
            sql: 'INSERT INTO users (email) VALUES ($1)',
            bindings: ['taken@example.com'],
            constraintName: 'users_email_unique',
            table: 'users',
        );
    }

    public function referencedRow(): Response
    {
        throw ForeignKeyConstraintViolationException::fromDriverError(
            previous: driverError('23503'),
            sql: 'DELETE FROM users WHERE id = $1',
            bindings: [1],
            constraintName: 'posts_user_id_fkey',
            table: 'posts',
        );
    }

    public function missingColumn(): Response
    {
        throw NotNullConstraintViolationException::fromDriverError(
            previous: driverError('23502'),
            sql: 'INSERT INTO users (email) VALUES ($1)',
            bindings: [null],
            table: 'users',
            column: 'email',
        );
    }
}

function signupRouter(
    string $action,
): Router {
    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $router = new RoutingBootstrapper(
        modules: [],
        container: $container,
        preferenceRegistry: $preferenceRegistry,
        classFileParser: new ClassFileParser(),
    )->boot();

    $container->get(RouteCollection::class)->add(new RouteDefinition(
        method: 'POST',
        path: '/signup',
        controller: SignupController::class,
        action: $action,
    ));

    return $router;
}

/**
 * @param array<string, string> $server
 */
function signupRequest(
    array $server = [],
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/signup', ...$server]);
}

describe('constraint violations through Router::handle()', function (): void {
    it('renders an uncaught unique violation from a controller as 409', function (): void {
        $response = signupRouter('duplicateEmail')->handle(signupRequest(['HTTP_ACCEPT' => 'application/json']));

        expect($response->statusCode())->toBe(409)
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Conflict.']);
    });

    it('renders an uncaught foreign key violation from a controller as 409', function (): void {
        $response = signupRouter('referencedRow')->handle(signupRequest());

        expect($response->statusCode())->toBe(409);
    });

    it('does not leak the constraint name, SQL or bindings into the 409 body', function (): void {
        $json = signupRouter('duplicateEmail')->handle(signupRequest(['HTTP_ACCEPT' => 'application/json']));
        $html = signupRouter('duplicateEmail')->handle(signupRequest());

        foreach ([$json->body(), $html->body()] as $body) {
            expect($body)->not->toContain('users_email_unique')
                ->and($body)->not->toContain('INSERT INTO')
                ->and($body)->not->toContain('taken@example.com');
        }
    });

    it('leaves a not-null violation unhandled so it surfaces as a server error', function (): void {
        $router = signupRouter('missingColumn');

        expect(fn () => $router->handle(signupRequest()))->toThrow(NotNullConstraintViolationException::class);
    });
});
