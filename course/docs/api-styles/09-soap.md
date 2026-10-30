# Lesson 9: SOAP

SOAP is the veteran: XML messages in an "envelope", described by a machine-readable contract called a **WSDL**. Few new APIs use it, but you'll meet it at banks, governments, insurers, payment providers and in older enterprise systems. Being able to consume it is a practical skill.

A SOAP call is always an HTTP **POST** of an XML document like this:

```xml
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                   xmlns:ns1="http://lab.localhost/books">
  <SOAP-ENV:Body>
    <ns1:GetBook>
      <ns1:id>2</ns1:id>
    </ns1:GetBook>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

## Where it lives in the lab

| | |
|---|---|
| Endpoint | `POST http://LAB_ADDR/api/soap` (from containers: `http://api:8000/api/soap`) |
| Contract | <http://LAB_ADDR/api/soap?wsdl> |
| Client page | <http://LAB_ADDR/client/soap> |
| Server code | `api/src/resources/soap/books.wsdl`, `SoapController`, `BookSoapService` |
| Library | PHP's built-in `soap` extension, enabled in both Dockerfiles |

Like GraphQL, SOAP runs **inside the existing `api` container**, on top of the same `Book` model.

## The contract: the WSDL

Open <http://LAB_ADDR/api/soap?wsdl> in your browser. Read it **bottom-up**:

```xml title="api/src/resources/soap/books.wsdl"
--8<-- "api/src/resources/soap/books.wsdl"
```

1. **service**: *where* the service lives. The server fills in its own URL; notice that it's different in your browser than from inside the client container.
2. **binding**: *how*: SOAP envelopes over HTTP, document/literal style.
3. **portType**: *what*: the operations, like an interface.
4. **message**: the input and output of each operation.
5. **types**: an XML Schema describing every element.

It's verbose, but completely machine-readable. A SOAP client needs nothing but this file.

## The server

The controller hands out the WSDL on GET, and passes POSTed envelopes to PHP's `SoapServer`:

```php title="api/src/app/Http/Controllers/SoapController.php"
--8<-- "api/src/app/Http/Controllers/SoapController.php"
```

`SoapServer` parses the XML, calls the method with the same name as the operation, and turns the returned array back into XML:

```php title="api/src/app/Soap/BookSoapService.php"
--8<-- "api/src/app/Soap/BookSoapService.php"
```

## Call it with curl

```bash
S=http://LAB_ADDR/api/soap

curl -s $S -H "Content-Type: text/xml" -d '
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://lab.localhost/books">
  <SOAP-ENV:Body>
    <ns1:GetBook><ns1:id>2</ns1:id></ns1:GetBook>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>'
```

Change the id to `999` and look at the answer. A `<SOAP-ENV:Fault>` is SOAP's error message, with a `faultcode` (`Client` = your fault, `Server` = theirs) and a `faultstring`.

That's a lot of typing for one call. In practice nobody writes envelopes by hand: a SOAP client builds them from the WSDL.

## Consume it from Laravel

```php title="client/src/app/Http/Controllers/SoapController.php"
--8<-- "client/src/app/Http/Controllers/SoapController.php"
```

`new SoapClient($wsdlUrl)` downloads the WSDL, and from then on every operation is a PHP method: `$client->GetBooks([...])`. With `'trace' => true` you can read the XML it sent and received. The client page shows both.

Open <http://LAB_ADDR/client/soap>, add a book, and then add one with year `5000` to see a SOAP fault.

!!! warning "The single-element gotcha"
    Without `SOAP_SINGLE_ELEMENT_ARRAYS`, PHP returns a list of **one** book as a single object instead of an array of one, and `foreach` breaks as soon as a search finds exactly one result. Remove the option, rebuild the client, and search for `dune` to see it happen.

## Exercises

1. **Read the WSDL.** Without looking at the PHP code, which operations exist, and which fields are required for `AddBook`?
2. **Watch the difference.** Open the WSDL in your browser and from the client container (`docker compose exec client php -r 'echo file_get_contents("http://api:8000/api/soap?wsdl");' | tail -5`). Why is the `soap:address` different?
3. **Add an operation.** Add `DeleteBook(apiKey, id)` to the WSDL (element, messages, portType, binding) and to `BookSoapService`, then call it from the client. Count how many places you had to change, and compare that with adding an endpoint in REST or GraphQL.
4. **Postman / SoapUI.** Import `http://LAB_ADDR/api/soap?wsdl` into Postman (Import → link) or SoapUI. Both generate example requests for every operation from the WSDL alone.
