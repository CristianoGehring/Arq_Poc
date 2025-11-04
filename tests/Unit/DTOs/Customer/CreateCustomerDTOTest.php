<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Customer;

use App\DTOs\Customer\CreateCustomerDTO;
use Tests\TestCase;

class CreateCustomerDTOTest extends TestCase
{
    public function test_can_create_dto_with_all_fields(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678900',
            phone: '11999999999',
            address: [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-567',
            ]
        );

        $this->assertEquals('João Silva', $dto->name);
        $this->assertEquals('joao@example.com', $dto->email);
        $this->assertEquals('12345678900', $dto->document);
        $this->assertEquals('11999999999', $dto->phone);
        $this->assertIsArray($dto->address);
        $this->assertEquals('Rua Exemplo', $dto->address['street']);
    }

    public function test_can_create_dto_with_required_fields_only(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'Maria Santos',
            email: 'maria@example.com',
            document: '98765432100',
        );

        $this->assertEquals('Maria Santos', $dto->name);
        $this->assertEquals('maria@example.com', $dto->email);
        $this->assertEquals('98765432100', $dto->document);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->address);
    }

    public function test_can_create_from_request_array(): void
    {
        $data = [
            'name' => 'Pedro Oliveira',
            'email' => 'pedro@example.com',
            'document' => '11122233344',
            'phone' => '11988888888',
            'address' => [
                'street' => 'Avenida Teste',
                'number' => '456',
                'city' => 'Rio de Janeiro',
                'state' => 'RJ',
                'zip_code' => '20000-000',
            ],
        ];

        $dto = CreateCustomerDTO::fromRequest($data);

        $this->assertInstanceOf(CreateCustomerDTO::class, $dto);
        $this->assertEquals('Pedro Oliveira', $dto->name);
        $this->assertEquals('pedro@example.com', $dto->email);
        $this->assertEquals('11122233344', $dto->document);
        $this->assertEquals('11988888888', $dto->phone);
        $this->assertIsArray($dto->address);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'Ana Costa',
            email: 'ana@example.com',
            document: '55566677788',
            phone: '11977777777',
            address: [
                'street' => 'Rua Principal',
                'number' => '789',
                'city' => 'Belo Horizonte',
                'state' => 'MG',
                'zip_code' => '30000-000',
            ]
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('document', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertArrayHasKey('address', $array);
        $this->assertEquals('Ana Costa', $array['name']);
        $this->assertEquals('ana@example.com', $array['email']);
    }

    public function test_from_request_handles_missing_optional_fields(): void
    {
        $data = [
            'name' => 'Carlos Lima',
            'email' => 'carlos@example.com',
            'document' => '99988877766',
        ];

        $dto = CreateCustomerDTO::fromRequest($data);

        $this->assertEquals('Carlos Lima', $dto->name);
        $this->assertEquals('carlos@example.com', $dto->email);
        $this->assertEquals('99988877766', $dto->document);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->address);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'Teste',
            email: 'teste@example.com',
            document: '12345678900',
        );

        $reflection = new \ReflectionClass($dto);

        $this->assertTrue($reflection->isReadOnly());
    }
}
