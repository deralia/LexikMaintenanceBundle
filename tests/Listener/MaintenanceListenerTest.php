<?php

namespace Lexik\Bundle\MaintenanceBundle\Tests\Listener;

use Lexik\Bundle\MaintenanceBundle\src\Drivers\DriverFactory;
use Lexik\Bundle\MaintenanceBundle\src\Drivers\FileDriver;
use Lexik\Bundle\MaintenanceBundle\src\Exception\ServiceUnavailableException;
use Lexik\Bundle\MaintenanceBundle\src\Listener\MaintenanceListener;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MaintenanceListenerTest extends TestCase
{
    public function testSubRequestIsIgnored(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $listener = new MaintenanceListener($factory);

        $request = Request::create('/');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testQueryBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            path:    null,
            host:    null,
            ips:     null,
            query:   ['foo' => '^bar$'],
            cookie:  [],
            route:   null,
            attributes: [],
            http_code: null,
            http_status: null,
            http_exception_message: null,
            debug:   false
        );

        $request = Request::create('/?foo=bar');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testCookieBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            query:   [],
            cookie:  ['ck' => '^val$']
        );

        $request = Request::create('/', 'GET', [], ['ck' => 'val']);
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testAttributesBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            query:   [],
            cookie:  [],
            route:   null,
            attributes: ['foo' => '^bar$']
        );

        $request = Request::create('/');
        $request->attributes->set('foo', 'bar');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testPathBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            path: '^/health$'
        );

        $request = Request::create('/health');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testIpBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            ips: ['10.0.0.1']
        );

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testRouteBypass(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            route: '^admin_'
        );

        $request = Request::create('/');
        $request->attributes->set('_route', 'admin_home');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testDebugBypassForInternalRoute(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(self::never())->method('getDriver');

        $listener = new MaintenanceListener(
            $factory,
            debug: true
        );

        $request = Request::create('/');
        $request->attributes->set('_route', '_internal');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testDriverDecideFalseDoesNotThrow(): void
    {
        $driver   = $this->getMockBuilder(FileDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver->method('decide')->willReturn(false);

        $factory  = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $listener = new MaintenanceListener($factory);

        $request = Request::create('/');
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        // no assertions; all good if no exceptions were thrown
        self::assertTrue(true);
    }

    public function testDriverDecideTrueThrowsException(): void
    {
        $driver   = $this->getMockBuilder(FileDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driver->method('decide')->willReturn(true);

        $factory  = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $msg      = 'Under maintenance';
        $listener = new MaintenanceListener(
            $factory,
            debug: false,
            http_exception_message: $msg
        );

        $request = Request::create('/');
        $kernel  = $this->createMock(HttpKernelInterface::class);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage($msg);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);
    }

    public function testOnKernelResponseRewritesStatusCode(): void
    {
        $factory  = $this->createMock(DriverFactory::class);
        $listener = new MaintenanceListener(
            $factory,
            http_code:   503,
            http_status: 'Service down'
        );

        // simulate that onKernelRequest set handleResponse = true
        $rp = new ReflectionProperty(MaintenanceListener::class, 'handleResponse');
        $rp->setAccessible(true);
        $rp->setValue($listener, true);

        $request  = Request::create('/');
        $response = new Response('', 200);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener->onKernelResponse($event);

        self::assertSame(503, $response->getStatusCode());
    }
}
