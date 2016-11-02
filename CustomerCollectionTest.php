<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class CustomerCollectionTest extends TestCase
{
    public function testBaseCollectionGood()
    {

        $this->visit('odata.svc/customer')
        ->see('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
        ->see('<feed xml:base="')
        ->see('xmlns:d=')
        ->see('xml:base="')
        ->see('xmlns:m="')
        ->see('xmlns="http://www.w3.org/2005/Atom"')
        ->see('<title type="text">customer</title>')
        ->see('odata.svc/customer</id>')
        ->see('<link rel="self" title="customer" href="customer"/>')
        ->see('<entry>')
        ->see('</entry>')
        ->see('</feed>')

        ->dontSee("Whoops");
    }

    public function testSingleCollection()
   {
        $this->visit('odata.svc/customer(1)')
        ->see('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
        ->see('<entry xml:base="http://')
        ->see('/customer(id=1)</id>')
        ->see('<title type="text">customer</title>')
        ->see('<author>')
        ->see('</author>')
        ->see('<link rel="http://schemas.microsoft.com/ado/2007/08/dataservices/related/staff" type="application/atom+xml;type=entry" title="staff" href="customer(id=1)/staff"/>')
        ->see('<category term="Data.customer" scheme="http://schemas.microsoft.com/ado/2007/08/dataservices/scheme"/>')
        ->see('<content type="application/xml"')
        ->see('<m:properties>')
        ->see('<d:id m:type="Edm.Int32">1</d:id>')
        ->see('<d:name m:type="Edm.String">Bilbo Baggins</d:name>')
        ->see('</m:properties>')
        ->see('</content>')
        ->see('</entry>')
        ->dontSee("Whoops");
   }

}
