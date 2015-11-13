$(document).ready(function () {
    $('#amember-login').change(function () {
        $('#email').val($(this).val());
    });

    $('#login').submit(function (e) {
        e.preventDefault();

        if ($('input[type="submit"]').prop("disabled") === true)
            return false;

        var username = $('#amember-login').val();
        var password = $('#amember-pass').val();

        if (!username || !password) {
            $('#alert').html("The user name and password fields must be filled in.").show();
            return false;
        }

        $.post($(this).attr("action"), {amember_login: username, amember_pass: password},
        function (response) {
            if (response.ok === true) {
                var logged = false;
                var tries = 0;
                while (logged === false && tries < 5) {
                    var request = $.ajax({
                        type: 'POST',
                        url: '/api/login/hook',
                        data: {nonce: $('#login').data("nonce")},
                        async: false,
                        success: function (response) {
                            logged = (response.status == 0);
                        }
                    });
                    tries++;
                }
                window.open("/home", "_self");
//                window.location.href = response.url;
            } else {
                $('#alert').hide().attr("class", "alert alert-error");
                if (response.code === -4) {
                    $('input[type="submit"]').prop("disabled", true);
                    var matches = /(\d+)/.exec(response.error[0]);
                    var count = matches[0];
                    var countdown = setInterval(function () {
                        $('#alert').html("Too many invalid login attempts.<br>You may try again in " + count + " seconds.").show();
                        count--;
                        if (count <= 0) {
                            $('#alert').hide();
                            $('input[type="submit"]').prop("disabled", false);
                            clearInterval(countdown);
                        }
                    }, 1000);
                } else {
                    $('#alert').html(response.error[0]).show();
                }

                $('input[type="password"]').val("");
            }
        }
        );
    });

    $('#askpass button[class$="btn-primary"]').click(function () {
        if (!$('#email').val().length)
            return false;

        $.post('/amember4/sendpass', {login: $('#email').val()},
        function (response) {
            if (response.error[0].indexOf("check your mailbox") != -1) {
                $('#alert').html("You have requested a password reset.<br>Please check your email.").attr("class", "alert alert-info").show();
                $('input[type="password"]').val("");
            } else {
                $('#alert').html("Your password reset request was denied.<br>You must provide a registered username or email address.").attr("class", "alert alert-error").show();
            }
        }
        );

        $('#askpass').modal("hide");
    });
});
