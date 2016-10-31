<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class MetadataTest extends TestCase
{
    public function testBasePageGood()
    {

        $this->visit('odata.svc')
        ->see('<service')
        ->see('xmlns:atom="http://www.w3.org/2005/Atom"')
        ->see('xmlns:app="http://www.w3.org/2007/app')
        ->see('xmlns="http://www.w3.org/2007/app"')
        ->see('xml:base="')
        ->see('</service>')
        ->see('<workspace>')
        ->see('</workspace>')
        ->see('<atom:title>Default</atom:title>')
        ->see('<collection href="customer">')
        ->see('<collection href="photo">')
        ->see('<collection href="staff">')
        ->dontSee("Whoops");
    }
}
