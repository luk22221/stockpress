<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // "image" rule is not used on purpose: it does not accept TIFF.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,tif,tiff,bmp', 'max:5120', 'dimensions:min_width=500,min_height=500'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Wybierz plik do przesłania.',
            'file.file' => 'Przesyłanie pliku nie powiodło się.',
            'file.uploaded' => 'Przesyłanie pliku nie powiodło się.',
            'file.mimes' => 'Dozwolone typy plików: JPG, PNG, WebP, TIFF, BMP.',
            'file.max' => 'Plik może mieć maksymalnie 5 MB.',
            'file.dimensions' => 'Obraz musi mieć co najmniej 500 × 500 px.',
            'name.required' => 'Podaj imię.',
            'name.string' => 'Imię musi być tekstem.',
            'name.max' => 'Imię może mieć maksymalnie 255 znaków.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'email.max' => 'Adres e-mail może mieć maksymalnie 255 znaków.',
        ];
    }
}
