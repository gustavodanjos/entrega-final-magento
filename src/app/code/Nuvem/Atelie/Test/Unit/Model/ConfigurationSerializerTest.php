<?php
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
}
