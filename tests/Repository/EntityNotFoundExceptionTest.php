<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Database\Exceptions\EntityNotFoundException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Request;

describe('EntityNotFoundException', function (): void {
    it('returns EntityNotFoundException from RepositoryException::entityNotFound', function (): void {
        $exception = RepositoryException::entityNotFound('App\\Blog\\Entity\\Post', 42);

        expect($exception)->toBeInstanceOf(EntityNotFoundException::class)
            ->and($exception->getMessage())->toBe("Entity 'App\\Blog\\Entity\\Post' with ID 42 not found")
            ->and($exception->entityClass)->toBe('App\\Blog\\Entity\\Post')
            ->and($exception->id)->toBe(42);
    });

    it('remains catchable as RepositoryException', function (): void {
        $caught = null;

        try {
            throw RepositoryException::entityNotFound('App\\Blog\\Entity\\Post', 'abc');
        } catch (RepositoryException $e) {
            $caught = $e;
        }

        expect($caught)->toBeInstanceOf(EntityNotFoundException::class);
    });

    it('maps to 404 with a generic message', function (): void {
        $exception = RepositoryException::entityNotFound('App\\Blog\\Entity\\Post', 42);

        expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
            ->and($exception->getStatusCode())->toBe(404)
            ->and($exception->getHeaders())->toBeEmpty()
            ->and($exception->getResponseData())->toBe(['message' => 'Not found.']);
    });

    it('renders 404 without leaking the entity class or ID', function (): void {
        $exception = RepositoryException::entityNotFound('App\\Blog\\Entity\\Post', 987654);
        $renderer = new ExceptionRenderer();

        $json = $renderer->render($exception, new Request(server: ['HTTP_ACCEPT' => 'application/json']));
        $html = $renderer->render($exception, new Request());

        expect($json->statusCode())->toBe(404)
            ->and($json->body())->not->toContain('Post')->not->toContain('987654')
            ->and($html->statusCode())->toBe(404)
            ->and($html->body())->not->toContain('Post')->not->toContain('987654');
    });
});
