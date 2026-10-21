<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use InvalidArgumentException;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Selection;
use PHPUnit\Framework\TestCase;

class ConfigurationSerializerTest extends TestCase
{
    public function testDifferentOrderProducesSameString(): void
    {
        $a = new Configuration(38, 'LUIZ', [
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
            new Selection('solado', 'SOL-LUA-HOLO'),
        ]);
        $b = new Configuration(38, 'LUIZ', [
            new Selection('solado', 'SOL-LUA-HOLO'),
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
        ]);

        $serializer = new ConfigurationSerializer();

        $this->assertSame(
            $serializer->serialize($a),
            $serializer->serialize($b)
        );
    }

    public function testSerializesInContractFormat(): void
    {
        $configuration = new Configuration(38, 'LUIZ', [
            new Selection('solado', 'SOL-LUA-HOLO'),
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
        ]);

        $serializer = new ConfigurationSerializer();

        $this->assertSame(
            '{"tamanho":38,"gravacao":"LUIZ","selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"},'
            . '{"grupo":"solado","componente":"SOL-LUA-HOLO"}]}',
            $serializer->serialize($configuration)
        );
    }

    public function testEngravingIsNormalized(): void
    {
        $serializer = new ConfigurationSerializer();

        $this->assertSame(
            $serializer->serialize(new Configuration(38, 'LUIZ')),
            $serializer->serialize(new Configuration(38, '  LUIZ  '))
        );

        $this->assertSame(
            '{"tamanho":38,"gravacao":null,"selecoes":[]}',
            $serializer->serialize(new Configuration(38, '   '))
        );
    }

    public function testMissingEngravingIsSerializedAsNull(): void
    {
        $serializer = new ConfigurationSerializer();

        $this->assertSame(
            '{"tamanho":38,"gravacao":null,"selecoes":[]}',
            $serializer->serialize(new Configuration(38))
        );
    }

    public function testDifferentConfigurationsProduceDifferentStrings(): void
    {
        $serializer = new ConfigurationSerializer();

        $withHolo = new Configuration(38, null, [new Selection('solado', 'SOL-LUA-HOLO')]);
        $withStandard = new Configuration(38, null, [new Selection('solado', 'SOL-BORRACHA-LAVANDA')]);
        $otherSize = new Configuration(40, null, [new Selection('solado', 'SOL-LUA-HOLO')]);

        $this->assertNotSame(
            $serializer->serialize($withHolo),
            $serializer->serialize($withStandard)
        );
        $this->assertNotSame(
            $serializer->serialize($withHolo),
            $serializer->serialize($otherSize)
        );
    }

    public function testDuplicatedGroupIsRejected(): void
    {
        $configuration = new Configuration(38, null, [
            new Selection('solado', 'SOL-LUA-HOLO'),
            new Selection('solado', 'SOL-BORRACHA-LAVANDA'),
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->serialize($configuration);
    }

    public function testUnserializeRoundTripsSerializedConfigurations(): void
    {
        $serializer = new ConfigurationSerializer();
        $configuration = new Configuration(40, ' LUIZ ', [
            new Selection('solado', 'SOL-LUA-HOLO'),
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
        ]);

        $this->assertSame(
            $serializer->serialize($configuration),
            $serializer->serialize($serializer->unserialize($serializer->serialize($configuration)))
        );
    }

    public function testUnserializeKeepsTheContractValues(): void
    {
        $unserialized = (new ConfigurationSerializer())->unserialize(
            '{"tamanho":38,"gravacao":"LUIZ","selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"}]}'
        );

        $this->assertSame(38, $unserialized->getSize());
        $this->assertSame(38, $unserialized->getTamanho());
        $this->assertSame('LUIZ', $unserialized->getEngraving());
        $this->assertSame('LUIZ', $unserialized->getGravacao());
        $this->assertCount(1, $unserialized->getSelections());
        $this->assertCount(1, $unserialized->getSelecoes());
        $this->assertSame('cabedal', $unserialized->getSelections()[0]->getGroup());
        $this->assertSame('cabedal', $unserialized->getSelections()[0]->getGrupo());
        $this->assertSame('CAB-TRICO-LAVANDA', $unserialized->getSelections()[0]->getComponent());
        $this->assertSame('CAB-TRICO-LAVANDA', $unserialized->getSelections()[0]->getComponente());
    }

    public function testUnserializeTreatsNullEngravingAsAbsent(): void
    {
        $unserialized = (new ConfigurationSerializer())->unserialize(
            '{"tamanho":38,"gravacao":null,"selecoes":[]}'
        );

        $this->assertNull($unserialized->getEngraving());
        $this->assertSame([], $unserialized->getSelections());
    }

    public function testUnserializeRejectsMalformedJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->unserialize('{not json');
    }

    public function testUnserializeRejectsMissingContractFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->unserialize('{"tamanho":38}');
    }

    public function testUnserializeRejectsFieldsWithTheWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->unserialize(
            '{"tamanho":"38","gravacao":null,"selecoes":[]}'
        );
    }

    public function testUnserializeRejectsIncompleteSelections(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->unserialize(
            '{"tamanho":38,"gravacao":null,"selecoes":[{"grupo":"solado"}]}'
        );
    }

    public function testUnserializeRejectsDuplicatedGroups(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfigurationSerializer())->unserialize(
            '{"tamanho":38,"gravacao":null,"selecoes":['
            . '{"grupo":"solado","componente":"SOL-LUA-HOLO"},'
            . '{"grupo":"solado","componente":"SOL-BORRACHA-LAVANDA"}]}'
        );
    }
}
