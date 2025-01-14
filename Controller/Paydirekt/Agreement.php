<?php

/**
 * PAYONE Magento 2 Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE Magento 2 Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE Magento 2 Connector. If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category  Payone
 * @package   Payone_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2003 - 2019 Payone GmbH
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      http://www.payone.de
 */

namespace Payone\Core\Controller\Paydirekt;

use Payone\Core\Model\PayoneConfig;

/**
 * Controller for paydirekt oneKlick registration
 */
class Agreement extends \Magento\Framework\App\Action\Action
{
    /**
     * PAYONE authorization request model
     *
     * @var \Payone\Core\Model\Api\Request\PaydirektAgreement
     */
    protected $paydirektAgreement;

    /**
     * Customer session object
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Checkout session object
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * PAYONE database helper
     *
     * @var \Payone\Core\Helper\Database
     */
    protected $databaseHelper;

    /**
     * Payone checkout helper
     *
     * @var \Payone\Core\Helper\Checkout
     */
    protected $checkoutHelper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Payone\Core\Model\Api\Request\PaydirektAgreement $paydirektAgreement
     * @param \Magento\Customer\Model\Session                   $customerSession
     * @param \Magento\Checkout\Model\Session                   $checkoutSession
     * @param \Payone\Core\Helper\Database                      $databaseHelper
     * @param \Payone\Core\Helper\Checkout                      $checkoutHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Payone\Core\Model\Api\Request\PaydirektAgreement $paydirektAgreement,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Payone\Core\Helper\Database $databaseHelper,
        \Payone\Core\Helper\Checkout $checkoutHelper
    ) {
        parent::__construct($context);
        $this->paydirektAgreement = $paydirektAgreement;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->databaseHelper = $databaseHelper;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * Set payment method to paydirekt and add some session params
     *
     * @return void
     */
    protected function prepareQuote()
    {
        $oQuote = $this->checkoutSession->getQuote();

        $oPayment = $oQuote->getPayment();
        $oPayment->setMethod(PayoneConfig::METHOD_PAYDIREKT);
        $oQuote->setPayment($oPayment);

        $oCustomer = $this->customerSession->getCustomer();

        if ($oCustomer->getDefaultBillingAddress()) {
            $oDefaultBilling = $oCustomer->getDefaultBillingAddress()->getDataModel();

            $oQuote->getBillingAddress()->importCustomerAddressData($oDefaultBilling);
            $oQuote->getBillingAddress()->setShouldIgnoreValidation(true);
        }

        if ($oCustomer->getDefaultShippingAddress()) {
            $oDefaultShipping = $oCustomer->getDefaultShippingAddress()->getDataModel();

            $oQuote->getShippingAddress()->importCustomerAddressData($oDefaultShipping);
            $oQuote->getShippingAddress()->setCollectShippingRates(true)->setShouldIgnoreValidation(true);
        }

        $oQuote->collectTotals()->save();

        $this->checkoutSession->setPayoneQuoteComparisonString($this->checkoutHelper->getQuoteComparisonString($oQuote));
    }

    /**
     * Redirect to payment-provider or to success page
     *
     * @return void
     */
    public function execute()
    {
        $oCustomer = $this->customerSession->getCustomer();
        if ($this->getRequest()->getParam('return') && (bool)$oCustomer->getPayonePaydirektRegistered() === false) {
            $oCustomer->setPayonePaydirektRegistered(1);
            $this->databaseHelper->markUserAsRegisteredWithPaydirekt($oCustomer->getId());
        }
        if ((bool)$oCustomer->getPayonePaydirektRegistered() === true) {
            $this->prepareQuote();
            $this->_redirect($this->_url->getUrl('payone/onepage/review'));
            return;
        }

        $oQuote = $this->checkoutSession->getQuote();
        $aResponse = $this->paydirektAgreement->sendAgreementRequest($oCustomer, $oQuote);
        if (isset($aResponse['status']) && $aResponse['status'] == 'REDIRECT' && !empty($aResponse['redirecturl'])) {
            $this->prepareQuote();
            $this->_redirect($aResponse['redirecturl']);
            return;
        }
        $this->_redirect($this->_url->getUrl('checkout'));
    }
}
