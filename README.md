CodeIgniter iDeal
=================
A CodeIgniter library to interact with the iDeal online payment method through the XML API, otherwise known as iDeal Professional, Advanced or 'Zelfbouw'. This implementation currently works for issuers which have only one endpoint for their API, which include ING Bank and Rabobank.

**Please read the documentation provided by your issuer carefully before implementing iDeal. The iDeal product has very strict requirements in how you handle transactions.**

Requirements
------------
1. PHP 5.1+
2. [CodeIgniter 2.0+](http://codeigniter.com)
3. libcurl with OpenSSL support
4. Phil Sturgeon's CodeIgniter [cURL library](http://github.com/philsturgeon/codeigniter-curl)
5. A bundle of public root certificates (e.g. http://curl.haxx.se/ca/cacert.pem)
6. An iDeal merchant account

Spark
-------------
This library is also released as a [Spark](http://getsparks.org). If you use this library in any other way, **don't copy the autoload.php to your config directory**.

Documentation
-------------

### Configuration
This library expects a configuration file to function correctly. A template for this file is provided with the library. These configuration settings are provided to you by your issuer.

### Issuers
In order to start a payment, the client has to select an issuer (bank). This result of the following function can be directly display by CodeIgniter's `form_dropdown` and are cached for a day.

    $this->ideal->get_directory();
    
### Payments
After picking an issuer, a new transaction has to be set up. This is done by calling the `set_transaction()` method and passing the identifier of the requested issuer and an purchase identifier. Additionally, you pass a made up entrance code (secret, used later), the total charged amount in cents and a description. This function will return an payment URL and a transaction identifier.

    $this->ideal->set_transaction('2014', 'AB9876', '707a28aa5', 1000, 'Purchase at BlueWaterBottles.com');
    
After the user has completed the payment, the status of the purchase should be verified. This can be done my calling the `get_status()` method and passing the transaction identifier along. This will return the status of the current transaction.

    $this->ideal->get_status('0020836032006186');

License
-------

This project is licensed under the MIT license.

Contributing
------------
I am a firm believer of social coding, so <strike>if</strike> when you find a bug, please fork my code on [GitHub](http://github.com/joelcox/codeigniter-ideal) and squash it. I will be happy to merge it back in to the code base (and add you to the "Thanks to" section). If you're not too comfortable using Git or messing with the inner workings of this library, please [open a new issue](http://github.com/joelcox/codeigniter-ideal/issues).

Thanks to
---------
* [Phil Sturgeon](http://philsturgeon.co.uk), for creating the CodeIgniter [cURL library](http://github.com/philsturgeon/codeigniter-curl) and thus taking care of all the cURL hassle.
