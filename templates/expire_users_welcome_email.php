<?php
/**
 * Email template for the "Expire User Roles" plugin.
 * Event: User Login
 * Destination: User
 * This template source is located in the plugin's templates folder. 
 * When UM Settings -> Emails is entered the email template is copied to your child theme folder {your-theme}/ultimate-member/email/expire_users_login_email.php
 * Version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="max-width: 560px;padding: 20px;background: #ffffff;border-radius: 5px;margin:40px auto;font-family: Open Sans,Helvetica,Arial;font-size: 15px;color: #666;">
	<div style="color: #444444;font-weight: normal;">
		<div style="text-align: center;font-weight:600;font-size:26px;padding: 10px 0;border-bottom: solid 3px #eeeeee;">{site_name}</div>
		<div style="clear:both"></div>
	</div>
	<div style="padding: 0 30px 30px 30px;border-bottom: 3px solid #eeeeee;">
		<div style="padding: 30px 0;font-size: 24px;text-align: center;line-height: 40px;">User Role Expiration<span style="display: block;">
			Your Account {username} is now included in our free User Role Expiration program. 
			Your User Role will be expired at {expiration-date}.
			We have enabled sending a Reminder email about renewal to you {expiration-reminder-days} days before you are expired. 
			You can renew your free User Role at your <a href="{expiration-url}">Account page</a></span>
		</div>
		<div style="padding:20px;">If you have any problems, please contact us at <a href="mailto:{admin_email}" style="color: #3ba1da;text-decoration: none">{admin_email}</a></div>
	</div>
	<div style="color: #999;padding: 20px 30px">
		<div style="">Thank you!</div>
		<div style="">
			The <a href="{site_url}" style="color: #3ba1da;text-decoration: none;">{site_name}</a> Team
		</div>
	</div>
</div>
