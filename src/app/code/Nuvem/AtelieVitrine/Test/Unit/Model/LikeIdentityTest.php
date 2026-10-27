<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Model;

use Magento\Customer\Model\Session;
use Nuvem\AtelieVitrine\Model\CreationLikes;
use Nuvem\AtelieVitrine\Model\LikeIdentity;
use PHPUnit\Framework\TestCase;

class LikeIdentityTest extends TestCase
{
    public function testAnonymousSessionGetsAnOpaqueServerGeneratedToken(): void
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getCustomerId'])->addMethods(['setData'])->getMock();
        $session->method('getData')->with('nuvem_like_visitor')->willReturn(null);
        $session->method('getCustomerId')->willReturn(null);
        $storedToken = null;
        $session->expects(self::once())->method('setData')->willReturnCallback(
            static function ($key, $token) use (&$storedToken, $session) {
                self::assertSame('nuvem_like_visitor', $key);
                $storedToken = $token;
                return $session;
            }
        );
        $likes = $this->createMock(CreationLikes::class);
        $likes->expects(self::never())->method('mergeGuestLikes');
        $identity = (new LikeIdentity($session, $likes))->get();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identity['visitor_token']);
        self::assertSame($storedToken, $identity['visitor_token']);
        self::assertSame(0, $identity['customer_id']);
    }

    public function testLoginKeepsTheVisitorTokenAndTransfersPreferencesToTheAuthenticatedAccount(): void
    {
        $token = str_repeat('a', 64);
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getCustomerId'])->addMethods(['setData'])->getMock();
        $session->method('getData')->with('nuvem_like_visitor')->willReturn($token);
        $session->method('getCustomerId')->willReturn(42);
        $session->expects(self::never())->method('setData');
        $likes = $this->createMock(CreationLikes::class);
        $likes->expects(self::once())->method('mergeGuestLikes')->with($token, 42);
        self::assertSame(
            ['customer_id' => 42, 'visitor_token' => $token],
            (new LikeIdentity($session, $likes))->get()
        );
    }

    public function testGuestNavigationRetainsTheExistingSessionIdentity(): void
    {
        $token = str_repeat('b', 64);
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getCustomerId'])->addMethods(['setData'])->getMock();
        $session->method('getData')->willReturn($token);
        $session->method('getCustomerId')->willReturn(null);
        $session->expects(self::never())->method('setData');
        $likes = $this->createMock(CreationLikes::class);
        $likes->expects(self::never())->method('mergeGuestLikes');
        $identity = new LikeIdentity($session, $likes);
        self::assertSame($token, $identity->get()['visitor_token']);
        self::assertSame($token, $identity->get()['visitor_token']);
    }
}
