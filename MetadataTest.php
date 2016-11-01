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
        ->see('<EntityType Name="staff">')
        ->see('<EntityType Name="customer">')
        ->see('<EntityType Name="photo" m:HasStream="true">')
        ->see('<Association Name="staff_partner">')
        ->see('<Association Name="staff_customers">')
        ->see('<Association Name="customer_staff">')
        ->see('<EntityContainer Name="Data" m:IsDefaultEntityContainer="true">')
        ->see('<EntitySet Name="staff" EntityType="Data.staff"/>')
        ->see('<EntitySet Name="customer" EntityType="Data.customer"/>')
        ->see('<EntitySet Name="photo" EntityType="Data.photo"/>')
        ->see('<AssociationSet Name="staff_partner_staff" Association="Data.staff_partner">')
        ->see('<AssociationSet Name="staff_customers_customer" Association="Data.staff_customers">')
        ->see('<AssociationSet Name="customer_staff_staff" Association="Data.customer_staff">')
        ->see('</Schema>')
        ->see('</edmx:DataServices>')
        ->dontSee("Whoops");

    }
}
