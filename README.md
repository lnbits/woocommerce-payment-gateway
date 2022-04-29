# LNBits for WooCommerce

Accept Bitcoin on your WooCommerce store, instantly over Lightning, and without extra fees.


## Setup
1. Create wallet on lnbits.com, or your own instance of LNBits
1. Install this plugin in your WordPress, and activate it (under admin -> Plugins)
2. Enable and configure LNBits payment (under admin -> WooCommerce -> Payments). You need to set `LNBits API Key` (you can get it from LNBits UI, under "Invoice/read key"), and `LNBits URL` (if you wanna use custom instance of LNBits)
3. Try it!

## How does it work?

### WordPress

[WordPress](https://wordpress.org/) is a popular framework for building websites. [WooCommerce](https://woocommerce.com/) is plugin for building e-shops within WordPress. This plugin adds payment gateway for accepting Bitcoin. If you have an e-shop powered by WordPress and WooCommerce, you can easily start accepting Bitcoin over Lightning.

### Lightning

[Lightning](https://en.wikipedia.org/wiki/Lightning_Network) is second layer on top of Bitcoin, primarily used for peer-to-peer payments. Kind of like Venmo/Zelle/etc, but open-sourced, trustless, and global. It's almost instant, and essentially free. As of 2021, it's pretty stable. The main complication for using Lightning is liquidity (ie. to receive payments, someone else must open a channel to you), and that's why it's worth using some app or service that helps maintaining liquidity, such as LNBits.

### LNBits

[LNBits](https://lnbits.org/) is a Lightning wallet which supports generating invoices through API. This plugin calls LNBits API to generate an invoice for each new order, and renders the invoice as a QR code, which the customer can scan with any Lightning wallet (ie. Wallet of Satoshi, Strike, or Breez), and it will pay the invoice, funds arriving in the LNBits wallet.

[LNBits.com](https://lnbits.com/) is a custodial service (ie. they hold the keys, so you need to trust them, until you move the funds to some non-custodial wallet), but it's super easy to setup. However, LNBits is open source, and you can later switch to hosting your own instance (ie. setting it up on Umbrel is really easy), and truly live in the spirit of Bitcoin...

### What happens under the hood?

The customer picks "Pay with Bitcoin over Lightning" from the list of payment options. The checkout page sends AJAX request in the background to the server, where this plugin calls Blockchain.info API to convert the total amount from fiat to Bitcoin and calls LNBits API to generate invoice. Then, the page redirects to LNBits payment page (standard WordPress page, which includes `[lnbits_payment_shortcode]` shortcode). This page renders QR code with the invoice so that the customer can easily scan it and pay with any Lightning wallet. In the background, the page keeps checking LNBits API and when the invoice gets paid, it redirects to standard WooCommerce "thank you" page.


## Contributing

If you find a bug, or have an idea for improvement, please [file an issue](https://gitlab.com/soverign-individuals/lnbits-for-woocommerce/-/issues/new) or send a pull request.



## Donation

If you find this plugin useful and would like to donate few sats to support the development, [send some using LNBits](https://legend.lnbits.com/paywall/YHNaeBc4nG2U4u6zyoHmjv)!
