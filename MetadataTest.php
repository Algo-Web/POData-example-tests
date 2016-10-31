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

    public function testMetadataPageGood()
    {
        $this->visit('/odata.svc/$metadata')
        ->see('<edmx:Edmx')
        ->see('<edmx:DataServices')
        ->see('<Schema Namespace=')
        ->see('<EntityType Name="Staff">')
        ->see('<EntityType Name="Customer">')
        ->see('<EntityType Name="Photo" m:HasStream="true">')
        ->see('<Association Name="Staff_Partner">')
        ->see('<Association Name="Staff_Customers">')
        ->see('<Association Name="Customer_Staff">')
        ->see('<EntityContainer Name="Data" m:IsDefaultEntityContainer="true">')
        ->see('<EntitySet Name="Staff" EntityType="Data.Staff"/>')
        ->see('<EntitySet Name="Customer" EntityType="Data.Customer"/>')
        ->see('<EntitySet Name="Photo" EntityType="Data.Photo"/>')
        ->see('<AssociationSet Name="Staff_Partner_Staff" Association="Data.Staff_Partner">')
        ->see('<AssociationSet Name="Staff_Customers_Customer" Association="Data.Staff_Customers">')
        ->see('<AssociationSet Name="Customer_Staff_Staff" Association="Data.Customer_Staff">')
        ->see('</Schema>')
        ->see('</edmx:DataServices>')
        ->dontSee("Whoops");

    }
}
