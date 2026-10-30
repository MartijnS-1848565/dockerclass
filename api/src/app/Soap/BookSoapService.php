<?php

namespace App\Soap;

use App\Models\Book;
use Illuminate\Support\Facades\Validator;
use SoapFault;

// Each public method is one SOAP operation from books.wsdl.
// PHP's SoapServer turns the incoming XML into a $params object, calls the method,
// and turns the returned array back into XML.
class BookSoapService
{
    public function GetBooks(object $params): array
    {
        $books = Book::query()->search($params->search ?? null)->orderBy('id')->get();

        return ['book' => $books->map(fn (Book $book) => $this->toArray($book))->all()];
    }

    public function GetBook(object $params): array
    {
        $book = Book::find($params->id);

        // A SoapFault becomes a <soap:Fault> in the response: SOAP's way of reporting errors.
        if (! $book) {
            throw new SoapFault('Client', "Book {$params->id} not found.");
        }

        return ['book' => $this->toArray($book)];
    }

    public function AddBook(object $params): array
    {
        $expected = config('api.key');

        // SOAP usually carries credentials inside the message (e.g. WS-Security headers),
        // not in HTTP headers. To keep it simple, the key is a field of the request here.
        if (! $expected || ! hash_equals($expected, (string) $params->apiKey)) {
            throw new SoapFault('Client', 'Invalid apiKey.');
        }

        $validator = Validator::make((array) $params, [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'between:0,2100'],
        ]);

        if ($validator->fails()) {
            throw new SoapFault('Client', $validator->errors()->first());
        }

        $book = Book::create($validator->validated());

        return ['book' => $this->toArray($book)];
    }

    private function toArray(Book $book): array
    {
        return $book->only('id', 'title', 'author', 'published_year');
    }
}
