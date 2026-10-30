<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SoapClient;
use SoapFault;

// PHP's SoapClient reads the WSDL and turns every operation into a method:
// $client->GetBooks([...]) builds the XML envelope, POSTs it, and parses the XML answer.
class SoapController extends Controller
{
    private function client(): SoapClient
    {
        return new SoapClient(config('bookapi.soap_wsdl'), [
            'trace' => true,                          // remember the raw XML, so we can show it
            'cache_wsdl' => WSDL_CACHE_NONE,          // always fetch the latest contract (fine for a lab)
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS, // a list with one book is still a list
            'connection_timeout' => 5,
            // connection_timeout doesn't cover downloading the WSDL itself; without this,
            // a dead API makes the page hang for PHP's default 60 seconds.
            'stream_context' => stream_context_create(['http' => ['timeout' => 5]]),
        ]);
    }

    public function index(Request $request)
    {
        $search = $request->query('search');
        $client = $this->client();

        $result = $client->GetBooks(array_filter(['search' => $search]));

        return view('soap.index', [
            'books' => $result->book ?? [],
            'search' => $search,
            'operations' => $client->__getFunctions(),
            'xml' => $this->lastXml($client),
        ]);
    }

    public function store(Request $request)
    {
        $client = $this->client();

        try {
            $result = $client->AddBook(array_filter([
                'apiKey' => config('bookapi.key'),
                'title' => $request->input('title'),
                'author' => $request->input('author'),
                'published_year' => $request->input('published_year'),
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (SoapFault $fault) {
            // The server answered with a <soap:Fault>, SOAP's error message.
            return back()->withInput()
                ->withErrors(['soap' => "SOAP fault ({$fault->faultcode}): {$fault->getMessage()}"])
                ->with('xml', $this->lastXml($client));
        }

        return redirect()->route('soap.index')
            ->with('status', "Created \"{$result->book->title}\" with AddBook (id {$result->book->id})")
            ->with('xml', $this->lastXml($client));
    }

    private function lastXml(SoapClient $client): array
    {
        return [
            'request' => $this->pretty($client->__getLastRequest()),
            'response' => $this->pretty($client->__getLastResponse()),
        ];
    }

    private function pretty(?string $xml): string
    {
        if (! $xml) {
            return '';
        }

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        return @$dom->loadXML($xml) ? $dom->saveXML() : $xml;
    }
}
