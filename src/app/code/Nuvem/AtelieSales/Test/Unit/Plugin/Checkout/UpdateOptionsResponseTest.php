<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Checkout;

use Magento\Checkout\Controller\Cart\UpdateItemOptions;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Message\Collection;
use Magento\Framework\Message\Error;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\Success;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Nuvem\AtelieSales\Plugin\Checkout\Controller\Cart\UpdateOptionsResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UpdateOptionsResponseTest extends TestCase
{
    public static function outcomes(): array
    {
        return [[true, true], [true, false], [false, false]];
    }

    #[DataProvider('outcomes')]
    public function testOnlyValidatedSuccessfulUpdatesCommit(bool $validFormKey, bool $success): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getParam')->with('id')->willReturn(42);
        $request->method('isAjax')->willReturn(true);
        $quote = $this->createMock(Quote::class);
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('atelie');
        $quote->method('getItemById')->with(42)->willReturn($item);
        $session = $this->createMock(Session::class);
        $session->method('getQuote')->willReturn($quote);
        $session->expects($validFormKey && !$success ? $this->once() : $this->never())
            ->method('replaceQuote')->with($quote);
        $validator = $this->createMock(Validator::class);
        $validator->method('validate')->with($request)->willReturn($validFormKey);
        $messages = $this->createMock(ManagerInterface::class);
        $previous = new Success('Previous message');
        $old = (new Collection())->addMessage($previous);
        $current = (new Collection())->addMessage($success ? new Success('Saved') : new Error('Invalid combination'));
        $messages->expects($validFormKey ? $this->exactly(2) : $this->never())->method('getMessages')
            ->with(true)->willReturnOnConsecutiveCalls($old, $current);
        $messages->expects($validFormKey ? $this->once() : $this->never())->method('addMessages')->with([$previous]);
        $json = $this->createMock(Json::class);
        $json->expects($this->once())->method('setData')->willReturnCallback(function (array $data) use ($json, $success) {
            $this->assertSame($success, $data['success']);
            $this->assertNotEmpty($data['messages']);
            return $json;
        });
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($validFormKey ? $this->once() : $this->never())->method('beginTransaction');
        $connection->expects($success ? $this->once() : $this->never())->method('commit');
        $connection->expects($validFormKey && !$success ? $this->once() : $this->never())->method('rollBack');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $factory = $this->createMock(QuoteFactory::class);
        $factory->method('create')->willReturn($quote);
        $quoteResource = $this->createMock(QuoteResource::class);
        $quoteResource->expects($validFormKey && !$success ? $this->once() : $this->never())->method('load');
        $plugin = new UpdateOptionsResponse(
            $request,
            $session,
            $validator,
            $messages,
            $jsonFactory,
            $resource,
            $this->createMock(LoggerInterface::class),
            $factory,
            $quoteResource
        );
        $called = false;
        $result = $plugin->aroundExecute($this->createMock(UpdateItemOptions::class), function () use (&$called) {
            $called = true;
        });
        $this->assertSame($validFormKey, $called);
        $this->assertSame($json, $result);
    }
}
