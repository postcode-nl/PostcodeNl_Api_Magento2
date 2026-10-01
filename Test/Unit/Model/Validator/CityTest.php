<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Model\Validator;

use Magento\Customer\Model\Customer;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Model\Validator\City;

/**
 * City field validation: allowed characters, length limit, and failure messages.
 */
class CityTest extends TestCase
{
    use MockCreationTrait;

    #[Test]
    public function null_city_passes_without_messages(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer(null));

        $this->assertTrue($result);
        $this->assertSame([], $validator->getMessages());
    }

    #[Test]
    #[DataProvider('validCityProvider')]
    public function allowed_characters_pass(string $city): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer($city));

        $this->assertTrue($result);
        $this->assertSame([], $validator->getMessages());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validCityProvider(): array
    {
        return [
            'letters' => ['Amsterdam'],
            'unicode letters' => ['Zürich'],
            'polish diacritics' => ['Kraków'],
            'portuguese diacritics' => ['São Paulo'],
            'german diacritics' => ['München'],
            'german sharp s' => ['Straße 5'],
            'combining mark' => ["Cafe\u{0301}"],
            'internal tab' => ["Amsterdam\tWest"],
            'straight apostrophe' => ["O'Brien"],
            'typographic apostrophe' => ['O’Brien'],
            'leading apostrophe and hyphen' => ["'s-Hertogenbosch"],
            'ampersand' => ['A&B'],
            'parentheses' => ['Test (West)'],
            'digits and hyphens' => ['1-2-3'],
            'comma ampersand and digits' => ['Price 1, 2 & 3'],
        ];
    }

    #[Test]
    #[DataProvider('lengthBoundaryProvider')]
    public function length_limit_enforced_at_boundary(string $city, bool $expected): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer($city));

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function lengthBoundaryProvider(): array
    {
        return [
            'exactly 100 characters' => [str_repeat('a', 100), true],
            '101 characters' => [str_repeat('a', 101), false],
        ];
    }

    #[Test]
    #[DataProvider('disallowedCharacterProvider')]
    public function disallowed_character_rejected_with_message(string $city): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer($city));

        $this->assertFalse($result);
        $messages = $validator->getMessages();
        $message = reset($messages);
        $this->assertIsArray($message);
        $this->assertArrayHasKey('city', $message);
        $this->assertStringContainsString('Invalid City', $message['city']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disallowedCharacterProvider(): array
    {
        return [
            'exclamation mark' => ['Amsterdam!'],
            'forward slash' => ['A/B'],
        ];
    }

    #[Test]
    public function empty_city_is_rejected(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer(''));

        $this->assertFalse($result);
        $messages = $validator->getMessages();
        $message = reset($messages);
        $this->assertIsArray($message);
        $this->assertArrayHasKey('city', $message);
    }

    #[Test]
    public function trailing_newline_within_length_is_accepted(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer("Amsterdam\n"));

        $this->assertTrue($result);
    }

    #[Test]
    public function trailing_newline_is_trimmed_before_length_check(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer(str_repeat('a', 100) . "\n"));

        $this->assertTrue($result);
    }

    #[Test]
    public function surrounding_whitespace_is_trimmed(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer('  Amsterdam  '));

        $this->assertTrue($result);
    }

    #[Test]
    public function internal_newline_is_rejected(): void
    {
        $validator = new City();

        $result = $validator->isValid($this->createCustomer("Amster\ndam"));

        $this->assertFalse($result);
        $messages = $validator->getMessages();
        $message = reset($messages);
        $this->assertIsArray($message);
        $this->assertArrayHasKey('city', $message);
    }

    #[Test]
    public function messages_do_not_accumulate_across_calls(): void
    {
        $validator = new City();

        $invalid = $validator->isValid($this->createCustomer('Amsterdam!'));
        $valid = $validator->isValid($this->createCustomer('Amsterdam'));

        $this->assertFalse($invalid);
        $this->assertTrue($valid);
        $this->assertSame([], $validator->getMessages());
    }

    private function createCustomer(?string $city): Customer
    {
        $customer = $this->createPartialMockWithReflection(Customer::class, ['getCity']);
        $customer->expects($this->once())->method('getCity')->willReturn($city);

        return $customer;
    }
}
