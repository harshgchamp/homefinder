<?php

use App\Models\Agency;
use App\Models\PropertyType;
use App\Models\User;
use App\Rules\UkPostcode;
use Illuminate\Support\Facades\Validator;

// --- The rule on its own ----------------------------------------------------------

test('accepts real UK postcode formats, ignoring spaces and case', function (string $postcode) {
    $validator = Validator::make(['postcode' => $postcode], ['postcode' => [new UkPostcode]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'A9 9AA' => 'M1 1AE',
    'A99 9AA' => 'B33 8TH',
    'AA9 9AA' => 'CR2 6XH',
    'AA99 9AA' => 'DN55 1PT',
    'A9A 9AA' => 'W1A 0AX',
    'AA9A 9AA' => 'EC1A 1BB',
    'lower case' => 'sw1a 1aa',
    'no space' => 'RM70AB',
    'extra spaces' => '  RM7   0AB ',
    'Girobank' => 'GIR 0AA',
]);

test('rejects things that are not UK postcodes', function (mixed $postcode) {
    $validator = Validator::make(['postcode' => $postcode], ['postcode' => [new UkPostcode]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('postcode'))
        ->toBe('The postcode must be a valid UK postcode, like SW1A 1AA.');
})->with([
    'US zip code' => '90210',
    'missing inward code' => 'RM7',
    'inward letters C I K M O V are never used' => 'RM7 0CI',
    'starts with a digit' => '1RM 0AB',
    'Q is never first' => 'QA1 1AA',
    'too long' => 'SW1A 1AAA',
    'not a string' => 12345,
]);

// --- Used in a Form Request ----------------------------------------------------------

test('the property form rejects a bad postcode with the friendly field name', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.properties.store'), [
            'agency_id' => Agency::factory()->create()->id,
            'property_type_id' => PropertyType::factory()->create()->id,
            'title' => 'Two bed flat',
            'description' => 'Bright flat near the station with a balcony.',
            'listing_type' => 'sale',
            'status' => 'available',
            'price' => 250000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'address_line_1' => '1 Station Road',
            'city' => 'Romford',
            'postcode' => '90210',
        ])
        ->assertSessionHasErrors(['postcode' => 'The postcode must be a valid UK postcode, like SW1A 1AA.']);
});
