<?php

namespace App\Http\Controllers;

use App\Soap\BookSoapService;
use Illuminate\Http\Request;
use SoapServer;

class SoapController extends Controller
{
    // GET /api/soap?wsdl: hand out the contract, with this server's own URL filled in.
    // Called from the client container that's http://api:8000/api/soap,
    // from your browser the proxy's address + /api/soap. url() takes the host from the request.
    public function wsdl()
    {
        $wsdl = str_replace('__LOCATION__', url('/api/soap'), file_get_contents($this->wsdlPath()));

        return response($wsdl, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    // POST /api/soap: every SOAP call arrives here as an XML envelope.
    // The <soap:Body> decides which method of BookSoapService runs.
    public function handle(Request $request)
    {
        $server = new SoapServer($this->wsdlPath(), ['cache_wsdl' => WSDL_CACHE_NONE]);
        $server->setObject(new BookSoapService);

        // SoapServer echoes its XML answer; capture it so Laravel can send it as a response.
        ob_start();
        $server->handle($request->getContent());
        $xml = ob_get_clean();

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    private function wsdlPath(): string
    {
        return resource_path('soap/books.wsdl');
    }
}
