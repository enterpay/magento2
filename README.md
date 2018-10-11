magento2_enterpay_ohje.pdf is outdated. Read this markdown:


How to integrate Enterpay payment method into Magento 2?

1. Download a module of our payment method from https://github.com/enterpay/magento2
2. Save this module to Magento's folder according to this path: app/code/Solteq/Enterpay (create necessary folders if they are missed)
3. Go to the root folder of Magento and run following commands:
	-sudo php bin/magento module:enable --clear-static-content Solteq_Enterpay
	-sudo php bin/magento setup:upgrade
	-sudo php bin/magento setup:di:compile
	-sudo php bin/magento cache:flush
	-sudo sudo php bin/magento setup:static-content:deploy en_US fi_FI
4. The module is successfully installed if all of commands which are mentioned above were run without any errors


Adjustments and settings

* Note: Before making any changes to Enterpay payment method settings, Enterpay should create a merchant in test (production) server.
* You can find settings related to our module via Magento admin panel: Stores → Configuration → Sales → Payment Methods → Enterpay
* Go to code/Solteq/Enterpay/Model/Enterpay.php to check which order fields are retrieved and how they are retrieved.
* Go to code/Solteq/Enterpay/Controller/Receipt/Index.php to check redirection URL


| Setting                  | Description                                                                                                                                                    | Optional Setting |
|--------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|:----------------:|
| Enabled                  | Payment method is visible to buyers on checkout                                                                                                                |        No        |
| Title                    | Payment method's name which is visible to buyers on checkout                                                                                                   |        No        |
| Merchant ID              | Merchant ID is unique identifier for merchant, which should be obtained from Enterpay. Note: merchant ID for test and production servers are different         |        No        |
| Merchant Secret          | Merchant key is a secret API key which should be obtained from Enterpay.  Note: merchant ID for test and production servers are different                      |        No        |
| Merchant Secret Version  | Merchant secret version is a version of API key. The value is 1 by default. If merchant wants to obtain a new key, the merchant secret version increments by 1 |        No        |
| Test Mode                | Must be set No in production and Yes for testing (Flag chooses between production URL checkout and test URL checkout)                                          |        No        |
| Debug Mode               | Must be set No in production and Yes for testing  (If yes integration error message will be shown for a developer)                                             |        No        |
| Invoice Reference Prefix | Invoice reference prefix which is created from prefix, Magento's order ID and  check ID                                                                        |        Yes       |
| Pending Order Status     | This is a purchase status, when purchase is marked as "pending" status. It means, a purchase will be manually approved by Enterpay                             |        No        |
| Approved Order Status    | This is a purchase status, when purchase is accepted immediately                                                                                               |        No        |
| Instructions             | There are instructions for this payment method which will be visible on checkout to buyers                                                                     |        Yes       |



* When settings are saved, clear cache from the terminal by running this command in the root folder: sudo php bin/magento cache:flush


Content Security Policy

* Some Magento installations have a setting that prevents redirection from web store to external services. This restriction as known as Content Policy form-action. If restriction is on then in our case it will be impossible to redirect to Enterpay payment service, so transaction will stop at Magento's checkout.
* Wrong restriction: content-security-policy: form-action 'self';
* Correct restriction: content-security-policy: form-action 'self' laskuyritykselle.fi *. laskuyritykselle.fi ;
