<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class CustomerCollectionTest extends TestCase
{
    public function testBaseCollectionGood()
    {

        $this->visit('odata.svc/Customer')
        ->see('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
        ->see('<feed xml:base="')
        ->see('xmlns:d=')
        ->see('xml:base="')
        ->see('xmlns:m="')
        ->see('xmlns="http://www.w3.org/2005/Atom"')
        ->see('<title type="text">Customer</title>')
        ->see('odata.svc/Customer</id>')
        ->see('<link rel="self" title="Customer" href="Customer"/>')
        ->see('<entry>')
        ->see('</entry>')
        ->see('</feed>')

        ->dontSee("Whoops");
    }
}
