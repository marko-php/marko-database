<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Throwable;

/**
 * An entity looked up by ID does not exist.
 *
 * Rendered by the routing pipeline as 404 with a generic message: the entity
 * class and ID stay in the exception (for logs) and are never sent to the
 * client.
 */
class EntityNotFoundException extends RepositoryException implements HttpExceptionInterface
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly int|string $id,
        string $message,
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            context: $context,
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return ['message' => 'Not found.'];
    }
}
