<?php

/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Controller\Creation;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\ProvisionalConfigurationValidator;
use Nuvem\AtelieVitrine\Controller\Creation\Save;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Service\SaveCreation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the saved creation controller.
 */
class SaveTest extends TestCase
{
    /**
     * Verify that guests cannot save creations.
     */
    public function testRejectsGuest(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(false);

        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->never())->method('execute');

        [$controller, $response] = $this->createController(
            $session,
            true,
            $this->createMock(ConfigurationValidatorInterface::class),
            $service,
            []
        );

        $controller->execute();

        $this->assertSame(401, $response->status);
        $this->assertFalse($response->data['success']);
    }

    /**
     * Verify that an invalid form key is rejected.
     */
    public function testRejectsInvalidFormKey(): void
    {
        $session = $this->loggedInSession();

        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->never())->method('execute');

        [$controller, $response] = $this->createController(
            $session,
            false,
            $this->createMock(ConfigurationValidatorInterface::class),
            $service,
            []
        );

        $controller->execute();

        $this->assertSame(400, $response->status);
        $this->assertFalse($response->data['success']);
    }

    /**
     * Verify that the provisional validator blocks saving.
     */
    public function testRejectsProvisionalValidator(): void
    {
        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->never())->method('execute');

        [$controller, $response] = $this->createController(
            $this->loggedInSession(),
            true,
            new ProvisionalConfigurationValidator(),
            $service,
            []
        );

        $controller->execute();

        $this->assertSame(503, $response->status);
        $this->assertFalse($response->data['success']);
    }

    /**
     * Verify that malformed configuration data is rejected.
     */
    public function testRejectsInvalidConfigurationPayload(): void
    {
        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->never())->method('execute');

        [$controller, $response] = $this->createController(
            $this->loggedInSession(),
            true,
            $this->createMock(ConfigurationValidatorInterface::class),
            $service,
            [
                'sku' => 'NUVEM-RUNNER',
                'name' => 'My Sneaker',
                'configuration' => '{"tamanho":"invalid","selecoes":[]}',
            ]
        );

        $controller->execute();

        $this->assertSame(400, $response->status);
        $this->assertFalse($response->data['success']);
    }

    /**
     * Verify that success returns only the public identifier.
     */
    public function testReturnsPublicHashWithoutInternalId(): void
    {
        $hash = str_repeat('a', 32);
        $creation = $this->createMock(SavedCreation::class);

        $creation->method('getData')
            ->with('hash')
            ->willReturn($hash);

        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->once())
            ->method('execute')
            ->with(
                42,
                'NUVEM-RUNNER',
                'My Sneaker',
                $this->callback(
                    static function (ConfigurationInterface $configuration): bool {
                        $selections = $configuration->getSelections();

                        return $configuration->getSize() === 38
                            && $configuration->getEngraving() === null
                            && count($selections) === 1
                            && $selections[0]->getGroup() === 'cabedal'
                            && $selections[0]->getComponent()
                                === 'CAB-TRICO-LAVANDA';
                    }
                )
            )
            ->willReturn($creation);

        [$controller, $response] = $this->createController(
            $this->loggedInSession(),
            true,
            $this->createMock(ConfigurationValidatorInterface::class),
            $service,
            [
                'sku' => 'NUVEM-RUNNER',
                'name' => 'My Sneaker',
                'configuration' => json_encode([
                    'tamanho' => 38,
                    'gravacao' => null,
                    'selecoes' => [
                        [
                            'grupo' => 'cabedal',
                            'componente' => 'CAB-TRICO-LAVANDA',
                        ],
                    ],
                ]),
            ]
        );

        $controller->execute();

        $this->assertSame(200, $response->status);
        $this->assertTrue($response->data['success']);
        $this->assertSame($hash, $response->data['hash']);
        $this->assertArrayNotHasKey('criacao_id', $response->data);
        $this->assertArrayNotHasKey('customer_id', $response->data);
    }

    /**
     * Verify that selections must be a JSON array.
     */
    public function testRejectsSelectionsObject(): void
    {
        $this->assertInvalidConfigurationPayload(
            '{"tamanho":38,"gravacao":null,"selecoes":{}}'
        );
    }

    /**
     * Verify that malformed JSON cannot be saved.
     */
    public function testRejectsMalformedJson(): void
    {
        $this->assertInvalidConfigurationPayload(
            '{"tamanho":38,"selecoes":['
        );
    }

    /**
     * Verify that missing selections are rejected.
     */
    public function testRejectsMissingSelections(): void
    {
        $this->assertInvalidConfigurationPayload(
            '{"tamanho":38,"gravacao":null}'
        );
    }

    /**
     * Verify that selection items must be objects.
     */
    public function testRejectsInvalidSelectionItem(): void
    {
        $this->assertInvalidConfigurationPayload(
            '{"tamanho":38,"gravacao":null,"selecoes":["invalid"]}'
        );
    }

    /**
     * Verify that the configuration root must be an object.
     */
    public function testRejectsConfigurationArray(): void
    {
        $this->assertInvalidConfigurationPayload('[]');
    }

    /**
     * Assert that invalid configurations never reach the save service.
     *
     * @param string $configuration
     * @return void
     */
    private function assertInvalidConfigurationPayload(
        string $configuration
    ): void {
        $service = $this->createMock(SaveCreation::class);
        $service->expects($this->never())->method('execute');

        [$controller, $response] = $this->createController(
            $this->loggedInSession(),
            true,
            $this->createMock(ConfigurationValidatorInterface::class),
            $service,
            [
                'sku' => 'NUVEM-RUNNER',
                'name' => 'My Sneaker',
                'configuration' => $configuration,
            ]
        );

        $controller->execute();

        $this->assertSame(400, $response->status);
        $this->assertFalse($response->data['success']);
    }

    /**
     * Create a logged-in customer session mock.
     *
     * @return CustomerSession
     */
    private function loggedInSession(): CustomerSession
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getCustomerId')->willReturn(42);

        return $session;
    }

    /**
     * Build the controller and capture its JSON response.
     *
     * @param CustomerSession $session
     * @param bool $validFormKey
     * @param ConfigurationValidatorInterface $validator
     * @param SaveCreation $service
     * @param array $params
     * @return array
     */
    private function createController(
        CustomerSession $session,
        bool $validFormKey,
        ConfigurationValidatorInterface $validator,
        SaveCreation $service,
        array $params
    ): array {
        $request = $this->createMock(RequestInterface::class);

        $request->method('getParam')
            ->willReturnCallback(
                static fn (string $key) => $params[$key] ?? null
            );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $formKey = $this->createMock(FormKeyValidator::class);
        $formKey->method('validate')->willReturn($validFormKey);

        $response = new class {
            /** @var int */
            public int $status = 200;

            /** @var array<string, mixed> */
            public array $data = [];
        };

        $json = $this->createMock(Json::class);

        $json->method('setHttpResponseCode')
            ->willReturnCallback(
                static function ($status) use ($response, $json) {
                    $response->status = $status;

                    return $json;
                }
            );

        $json->method('setData')
            ->willReturnCallback(
                static function ($data) use ($response, $json) {
                    $response->data = $data;

                    return $json;
                }
            );

        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $controller = new Save(
            $context,
            $jsonFactory,
            $formKey,
            $session,
            $validator,
            $service
        );

        return [$controller, $response];
    }
}
