<?php
/**
 * Email template for the "Expire User Roles" plugin.
 * Event: User Role Expired
 * Destination: Admin
 * This template source is located in the plugin's templates folder. 
 * When UM Settings -> Emails is entered the email template is copied to your child theme folder {your-theme}/ultimate-member/email/expire_users_admin_email.php
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
		<div style="padding: 30px 0;font-size: 24px;text-align: center;line-height: 40px;">
			User Role Expired<span style="display: block;">
			This Account {username} User Role has expired from the {expiration-role} Role today {expiration-date}.
			Email with expiration info was sent to {email}. 
			<a href="{user_profile_link}">User profile</a></span>
		</div>
	</div>
	<div style="color: #999;padding: 20px 30px">
		<div style="">Thank you!</div>
		<div style="">
			The <a href="{site_url}" style="color: #3ba1da;text-decoration: none;">{site_name}</a> Team
		</div>
	</div>
</div>
