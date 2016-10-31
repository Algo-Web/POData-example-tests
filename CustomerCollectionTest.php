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
}
