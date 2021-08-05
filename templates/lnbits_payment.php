<img id="qr_invoice" src="https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=<?php echo $invoice ?>&choe=UTF-8"/>
<b><?php echo $invoice ?></b><br/>


<script type="text/javascript">
	var $ = jQuery;
	var check_payment_url = '<?php echo $check_payment_url ?>';
	var order_id = <?php echo $order_id ?>;

	setInterval(function() {
		$.post(check_payment_url, {'order_id': order_id}).done(function(data) {
			var response = $.parseJSON(data);

			console.log(response);

			if (response['paid']) {
				window.location.replace(response['redirect']);
			}
		});

	}, 5000);
	
</script>