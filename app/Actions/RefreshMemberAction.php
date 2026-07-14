<?php
namespace SerbenConnect\Actions;
use SerbenConnect\Providers\UserDataProvider;
if (!defined('ABSPATH')) { exit; }
class RefreshMemberAction
{
    public function register():void { add_action('admin_post_serben_refresh_member',[$this,'handle']); }
    public function handle():void
    {
        if(!is_user_logged_in()){wp_safe_redirect(wp_login_url());exit;}
        check_admin_referer('serben_refresh_member');
        $provider=new UserDataProvider(); $provider->clearCurrent(); $provider->current(true);
        $redirect=wp_get_referer()?:home_url('/');
        $redirect=add_query_arg('serben_refreshed','1',$redirect);
        wp_safe_redirect($redirect); exit;
    }
}
