<?php

/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Selection;
use Nuvem\Atelie\Model\ValidationResult;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationFactory;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use Nuvem\AtelieVitrine\Service\SaveCreation;
use Nuvem\Atelie\Model\ProvisionalConfigurationValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the saved creation service.
 */
class SaveCreationTest extends TestCase
{
    /** @var ConfigurationValidatorInterface */
    private ConfigurationValidatorInterface $validator;

    /** @var SavedCreationFactory */
    private SavedCreationFactory $factory;

    /** @var SavedCreationRepository */
    private SavedCreationRepository $repository;

    /** @var SaveCreation */
    private SaveCreation $service;

    /**
     * Initialize the service with mocked dependencies.
     */
    protected function setUp(): void
    {
        $this->validator = $this->createMock(ConfigurationValidatorInterface::class);
        $this->factory = $this->createMock(SavedCreationFactory::class);
        $this->repository = $this->createMock(SavedCreationRepository::class);

        $this->service = new SaveCreation(
            $this->validator,
            new ConfigurationSerializer(),
            $this->factory,
            $this->repository
        );
    }

    /**
     * Verify that valid configurations are normalized and saved.
     */
    public function testSavesValidCreation(): void
    {
        $configuration = new Configuration(38, '  LUIZ  ');
        $creation = $this->newCreation();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with('NUVEM-RUNNER', $configuration)
            ->willReturn(new ValidationResult(true, []));

        $this->factory->expects($this->once())
            ->method('create')
            ->willReturn($creation);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($creation)
            ->willReturnCallback(
                function (SavedCreation $saved) use ($creation): SavedCreation {
                    $this->assertSame(42, $saved->getData('customer_id'));
                    $this->assertSame('NUVEM-RUNNER', $saved->getData('sku'));
                    $this->assertSame('My Sneaker', $saved->getData('nome'));
                    $this->assertSame(0, $saved->getData('publica'));
                    $this->assertSame(0, $saved->getData('curtidas'));

                    $this->assertSame(
                        '{"tamanho":38,"gravacao":"LUIZ","selecoes":[]}',
                        $saved->getData('configuracao')
                    );

                    $this->assertMatchesRegularExpression(
                        '/^[a-f0-9]{32}$/',
                        $saved->getData('hash')
                    );

                    return $creation;
                }
            );

        $result = $this->service->execute(
            42,
            ' NUVEM-RUNNER ',
            ' My Sneaker ',
            $configuration
        );

        $this->assertSame($creation, $result);
    }

    /**
     * Verify that invalid configurations cannot be saved.
     */
    public function testRejectsInvalidConfiguration(): void
    {
        $configuration = new Configuration(38);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ValidationResult(false, ['Invalid component']));

        $this->factory->expects($this->never())
            ->method('create');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            42,
            'NUVEM-RUNNER',
            'My Sneaker',
            $configuration
        );
    }

    /**
     * Verify that an invalid customer ID is rejected.
     */
    public function testRejectsInvalidCustomer(): void
    {
        $this->validator->expects($this->never())
            ->method('validate');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            0,
            'NUVEM-RUNNER',
            'My Sneaker',
            new Configuration(38)
        );
    }

    /**
     * Verify that an empty creation name is rejected.
     */
    public function testRejectsEmptyName(): void
    {
        $this->validator->expects($this->never())
            ->method('validate');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            42,
            'NUVEM-RUNNER',
            '   ',
            new Configuration(38)
        );
    }

    /**
     * Verify that creation names longer than 80 characters are rejected.
     */
    public function testRejectsNameLongerThan80Characters(): void
    {
        $this->validator->expects($this->never())
            ->method('validate');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            42,
            'NUVEM-RUNNER',
            str_repeat('A', 81),
            new Configuration(38)
        );
    }

    /**
     * Verify that an empty SKU is rejected.
     */
    public function testRejectsEmptySku(): void
    {
        $this->validator->expects($this->never())
            ->method('validate');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            42,
            '   ',
            'My Sneaker',
            new Configuration(38)
        );
    }

    /**
     * Verify that duplicate selection groups are rejected.
     */
    public function testRejectsDuplicateGroups(): void
    {
        $configuration = $this->createMock(ConfigurationInterface::class);

        $configuration->method('getSize')->willReturn(38);
        $configuration->method('getEngraving')->willReturn(null);

        $selection = new Selection('solado', 'SOL-LUA-HOLO');

        $configuration->method('getSelections')
            ->willReturn([$selection, $selection]);

        $this->validator->method('validate')
            ->willReturn(new ValidationResult(true, []));

        $this->factory->method('create')
            ->willReturn($this->newCreation());

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);

        $this->service->execute(
            42,
            'NUVEM-RUNNER',
            'My Sneaker',
            $configuration
        );
    }

    /**
     * Create a saved creation model for testing.
     *
     * @return SavedCreation
     */
    private function newCreation(): SavedCreation
    {
        return $this->getMockBuilder(SavedCreation::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Verify that the provisional validator cannot persist creations.
     */
    public function testRejectsProvisionalValidator(): void
    {
        $service = new SaveCreation(
            new ProvisionalConfigurationValidator(),
            new ConfigurationSerializer(),
            $this->factory,
            $this->repository
        );

        $this->factory->expects($this->never())
            ->method('create');

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Creation saving requires the real configuration validator.'
        );

        $service->execute(
            42,
            'NUVEM-RUNNER',
            'My Sneaker',
            new Configuration(38)
        );
    }
}
