<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
     <script src=https://touchpay.gutouch.com/touchpayv2/script/prod_touchpay-0.0.1.js  type="text/javascript"></script>
</head>
<body>
<input type=button onclick='calltouchpay()' value="{{$button_text}}" />
<script>
    function calltouchpay() {
        sendPaymentInfos(
            "{{ $order_number }}",
            "KOIFF9271",
            "OnWfTwa6c17eT23vjEf3k7t7Kiu5gULBn1nOwHUtPfdg6XQIho",
            "koiffure.com",
            "{{ $url_redirection_success }}",
            "{{ $url_redirection_failed }}",
            "{{ $amount }}",
            "Douala",
            "{{ $email }}",
            "{{ $clientFirstName }}",
            "{{ $clientLastName }}",
            "{{ $clientPhone }}"
        );
    }
</script>
</body>
</html>