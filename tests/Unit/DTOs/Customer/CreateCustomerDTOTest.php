<?php

namespace Tests\Unit\DTOs\Customer;

use App\DTOs\Customer\CreateCustomerDTO;
use App\Enums\CustomerStatus;
use Tests\TestCase;

class CreateCustomerDTOTest extends TestCase
{
    /** @test */
    public function it_creates_dto_from_array(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'document' => '12345678900',
            'phone' => '11999999999',
            'address' => [
                'street' => 'Rua Teste',
                'number' => '123',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zip_code' => '01000000',
            ],
        ];

        $dto = CreateCustomerDTO::fromRequest($data);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals('12345678900', $dto->document);
        $this->assertEquals('11999999999', $dto->phone);
        $this->assertEquals('Rua Teste', $dto->address['street']);
    }

    /** @test */
    public function it_converts_dto_to_array(): void
    {
        $dto = new CreateCustomerDTO(
            name: 'John Doe',
            email: 'john@example.com',
            document: '12345678900',
            phone: '11999999999',
            address: [
                'street' => 'Rua Teste',
                'number' => '123',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zip_code' => '01000000',
            ],
        );

        $array = $dto->toArray();

        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals('12345678900', $array['document']);
        $this->assertEquals('11999999999', $array['phone']);
        $this->assertEquals('Rua Teste', $array['address']['street']);
        $this->assertEquals(CustomerStatus::ACTIVE->value, $array['status']);
    }
}
