
aMember-Joomla/Community Builder Integration plugin

1. Introduction

This plugin is an addon for Joomla’s CommunityBuilder module that allows you to integrate Joomla with aMember. What does it do:

    when user registers in Joomla, corresponding user account will be added to aMember. Optionally, added user may be subscribed to configured aMember product. By obvious reasons, this can only be used to give user a free subscription, it won’t redirect to payment page/etc. So this plugin is USELESS if you are selling paid subscriptions to your Joomla content.
    when user changes password, name or email in Joomla, password will be also changed for corresponding aMember user;
    when user deleted from Joomla database, corresponding user will be also deleted from aMember database with all corresponding subscriptions/etc. Be careful with this!
    when user logs-in into or logs-out from Joomla, it also logs-in and logs-out from aMember.

It does not work vice-versa as most aMember plugins described below!
2. Setup

    Install and configure aMember Pro.
    Download, install and configure Joomla. http://www.joomla.org/
    Download, install and configure Community Builder. http://www.joomlapolis.com/
    Download, install and configure Community Builder Login Module (do not forget set 'Module Assignment' at 'On all page').

You must get Joomla and Comminity Builder completely configured and working before you go to next setup. CGI-Central does not provide any support with configuration of these third-party scripts.

3. Configure aMember

    3.1. Enable 'api' module  at 'aMember CP -> Setup/Configuration -> Plugins -> Enabled Modules'
    3.2. Create 'New record' at  'aMember CP -> Remote API Permosions' with next options:
        -Comment - of your choise
        -Api Key - can be left unchanged, or specify your own key (remember it for next configuring)
        -At Permissions check the next:
            *users - Users: all
            *products - Products: index, get, post
            *billing-plans - Product Billing Plans: index, get
            *access - Access: index, post
            *check-access - Check User Access: by-login-pass
    3.3. Click 'Save' button

4. Configure Joomla
    4.1. Download and unzip plugin joomla-cb-amember.zip
    4.2. Unpack the plugin and upload 'plug_amemberconnector' folder it to temp folder of your server with joomla, i.e. /html_public/joomla/tmp/plug_amemberconnector/
    4.3. Go to 'Joomla Admin CP -> choose menu Components -> CommunityBuilder -> Plugin Management'. Scroll down to “Install from directory” input,
        enter full path to plugin files, i.e. /html_public/joomla/tmp/plug_amemberconnector/
        press 'Install' button
    At 'Joomla Admin CP -> choose menu Components -> CommunityBuilder -> Plugin Management' page click on the 'aMember Connector' string for configure
    Full Amember settings fileds at parameters (Api key is the same at par. 3.2)
    Click 'Apply' button.
    If it's need choose product at 'Add a subscription' field.
    Click 'Save' button.

Now is the time to try that. Go to Joomla frontpage and register an account. It must happen without any problems.
Go to aMember Admin CP, and have a look – there must be a user created with exactly the same name, username and email.
Custom added fields will not be propagated.

This plugin is in ALPHA stage. It was tested with joomla version 2.5, Community Builder version 1.9