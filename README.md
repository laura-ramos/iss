# Invoicing Simple Service (ISS)

## Introduction
This module allows site to invoicing with mexican laws service. Module work with Factura Digital México to generate invoices.


## Requirements
This module requires the following modules:
[Drupal module PPSS](https://www.drupal.org/project/ppss)
[Drupal module Stripe Gateway](https://www.drupal.org/project/stripe_gateway)

## Installation
- Install as you would normally install a contributed Drupal module. See [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules) for more details.

## Configuration

In Factura Digital
- Register to [Factura digital](https://www.facturadigital.com.mx/) if you haven't yet.
- Enter your information in Configuracion >> Mis datos fiscales
- Create your folios and series in Configuracion >> Folios y series
- Upload your Digital Seal Certificates in Configuracion >> Certificados CSD

In Drupal main menu go to: Configuration » Invoicing Simple Service
- Enter endpoint url
If you are in development mode enter https://app.facturadigital.com.mx
If your are in sandbox mode enter https://sandbox-app.facturadigital.com.mx 

- Enter Api Key 
To obtain the api key you must log into your account at https://app.facturadigital.com.mx

- Enter Régimen Fiscal
- Enter expedition place
- Enter Serie and Folio
You must generate your folio and series in your account.
- Select method of payment
This method apply to all your products.
- Enter Number of records
Invoices to be generated every time the cron runs.
- Date to generate invoices
Date to generate invoices for the general public.

- Enable permissions:
   View iss Block

- Add ISS Invoicing Block


## How it works
This module allows you to generate invoices for each payment made, to generate the invoice the user must register their billing information.

We use [Factura digital](https://www.facturadigital.com.mx/) Api to generate invoices.