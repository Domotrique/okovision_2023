document.body.addEventListener('change', function (e) {
    let target = e.target;
    switch (target.id) {
        case 'oko_typeconnect_ip':
            $("#form-ip").show();
            break;
        case 'oko_typeconnect_usb':
            $("#form-ip").hide();
            break;
        case 'oko_loadingmode_silo':
            $("#form-silo-details").show();
            break;
        case 'oko_loadingmode_bags':
            $("#form-silo-details").hide();
            break;
    }
});