<?php

namespace Lexik\Bundle\MaintenanceBundle\Tests\Exception;

use Lexik\Bundle\MaintenanceBundle\src\Exception\ServiceUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ServiceUnavailableExceptionTest extends TestCase
{
    public function testIsHttpException(): void
    {
        $e = new ServiceUnavailableException();
        self::assertInstanceOf(HttpExceptionInterface::class, $e);
    }

    public function testDefaultStatusCodeIs503(): void
    {
        $e = new ServiceUnavailableException();
        self::assertSame(503, $e->getStatusCode());
    }

    public function testDefaultMessageIsEmptyString(): void
    {
        $e = new ServiceUnavailableException();
        // RuntimeException stores message as string even if null passed
        self::assertSame('', $e->getMessage());
    }

    public function testCustomMessageIsPassedThrough(): void
    {
        $message = 'Service is down for maintenance';
        $e = new ServiceUnavailableException($message);
        self::assertSame($message, $e->getMessage());
    }

    public function testPreviousExceptionIsStored(): void
    {
        $prev = new \Exception('inner error', 99);
        $e = new ServiceUnavailableException('foo', $prev);
        self::assertSame($prev, $e->getPrevious());
        // code (fifth argument) defaults to 0
        self::assertSame(0, $e->getCode());
    }

    public function testCustomCodeIsStored(): void
    {
        $prev = new \RuntimeException('inner');
        $code = 1234;
        $e = new ServiceUnavailableException('bar', $prev, $code);
        self::assertSame($prev, $e->getPrevious());
        self::assertSame($code, $e->getCode());
    }

    public function testHeadersAreEmptyArray(): void
    {
        $e = new ServiceUnavailableException('msg');
        self::assertSame([], $e->getHeaders());
    }
}
