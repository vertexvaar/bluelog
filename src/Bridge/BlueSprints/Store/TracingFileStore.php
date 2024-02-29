<?php

declare(strict_types=1);

namespace VerteXVaaR\BlueLog\Bridge\BlueSprints\Store;

use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use VerteXVaaR\BlueSprints\Mvcr\Model\Entity;
use VerteXVaaR\BlueSprints\Store\FileStore;
use VerteXVaaR\BlueSprints\Store\Store;

use function getenv;

readonly class TracingFileStore implements Store
{
    protected bool $enabled;

    public function __construct(
        private FileStore $inner,
    ) {
        $this->enabled = !empty(getenv('SENTRY_DSN'));
    }

    public function findByIdentifier(string $class, string $identifier): ?object
    {
        $transaction = $this->startTrace('findByIdentifier');
        try {
            return $this->inner->findByIdentifier($class, $identifier);
        } finally {
            $this->endTrace($transaction);
        }
    }

    public function findAll(string $class): array
    {
        $transaction = $this->startTrace('findAll');
        try {
            return $this->inner->findAll($class);
        } finally {
            $this->endTrace($transaction);
        }
    }

    public function store(Entity $entity): void
    {
        $transaction = $this->startTrace('store');
        try {
            $this->inner->store($entity);
        } finally {
            $this->endTrace($transaction);
        }
    }

    public function delete(Entity $entity): void
    {
        $transaction = $this->startTrace('delete');
        try {
            $this->inner->delete($entity);
        } finally {
            $this->endTrace($transaction);
        }
    }

    private function startTrace(string $action): ?Transaction
    {
        if (!$this->enabled) {
            return null;
        }
        $transactionContext = new TransactionContext();
        $transactionContext->setName('Filesystem Operation');
        $transactionContext->setOp($action);

        $transaction = SentrySdk::getCurrentHub()->startTransaction($transactionContext);

        // Set the current transaction as the current span so we can retrieve it later
        SentrySdk::getCurrentHub()->setSpan($transaction);

        // Setup the context for the expensive operation span
        $spanContext = new SpanContext();
        $spanContext->setOp($action);

        // Start the span
        $span = $transaction->startChild($spanContext);

        // Set the current span to the span we just started
        SentrySdk::getCurrentHub()->setSpan($span);

        return $transaction;
    }

    private function endTrace(?Transaction $transaction)
    {
        if (!$this->enabled || null === $transaction) {
            return null;
        }
        SentrySdk::getCurrentHub()->getSpan()->finish();

        // Set the current span back to the transaction since we just finished the previous span
        SentrySdk::getCurrentHub()->setSpan($transaction);

        // Finish the transaction, this submits the transaction and it's span to Sentry
        $transaction->finish();
    }
}
