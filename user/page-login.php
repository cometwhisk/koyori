<?php
/**
 * Theme login page for the /login route.
 */

if (!defined('ABSPATH')) {
    exit;
}

$login_error = isset($_GET['login']) && $_GET['login'] === 'failed';
$logged_out = isset($_GET['loggedout']) && $_GET['loggedout'] === 'true';
$redirect_to = isset($_REQUEST['redirect_to']) ? wp_validate_redirect((string) $_REQUEST['redirect_to'], home_url('/')) : home_url('/');

get_header();
?>
<style>
.koyori-login-page{max-width:460px;margin:40px auto 80px;padding:30px 24px;border:1px solid rgba(255,255,255,.72);border-radius:22px;background:rgba(255,255,255,.68);box-shadow:0 12px 34px rgba(68,43,73,.09);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.koyori-login-page h1{margin:0 0 24px;text-align:center;color:var(--global-font-color);font-size:26px}
.koyori-login-page label{display:block;margin:0 0 7px;color:var(--global-font-color);font-size:14px}
.koyori-login-page input[type=text],.koyori-login-page input[type=password]{box-sizing:border-box;width:100%;margin:0 0 16px;padding:11px 13px;border:1px solid rgba(120,100,130,.25);border-radius:10px;background:rgba(255,255,255,.72);color:var(--global-font-color);font-size:15px}
.koyori-login-page .login-remember{display:flex;align-items:center;gap:7px;margin:0 0 16px;color:var(--global-font-color);font-size:13px}
.koyori-login-page .login-remember input{margin:0}
.koyori-login-page .cf-turnstile{margin:4px 0 18px}
.koyori-login-page .login-submit{margin:0}
.koyori-login-page button{width:100%;padding:11px 16px;border:0;border-radius:10px;background:var(--theme-skin-matching,#b58bd2);color:#fff;font-size:15px;cursor:pointer}
.koyori-login-page .login-message{margin:0 0 18px;padding:10px 12px;border-radius:10px;background:rgba(221,75,91,.12);color:#b42336;font-size:13px}
.koyori-login-page .login-success{margin:0 0 18px;padding:10px 12px;border-radius:10px;background:rgba(66,153,104,.12);color:#28734a;font-size:13px}
.koyori-login-page .login-links{display:flex;justify-content:space-between;gap:12px;margin-top:18px;font-size:13px}
body.dark .koyori-login-page{border-color:rgba(100,100,100,.35);background:var(--dark-bg-secondary);box-shadow:var(--dark-shadow-normal)}
body.dark .koyori-login-page input[type=text],body.dark .koyori-login-page input[type=password]{border-color:rgba(180,180,180,.2);background:rgba(255,255,255,.06)}
@media (max-width:520px){.koyori-login-page{margin:24px 12px 60px;padding:24px 18px}}
</style>

<main id="primary" class="content-area">
    <div class="koyori-login-page">
        <h1>登录</h1>
        <?php if ($login_error): ?>
            <p class="login-message" role="alert">用户名或密码不正确，请重试。</p>
        <?php elseif ($logged_out): ?>
            <p class="login-success" role="status">你已安全退出。</p>
        <?php endif; ?>
        <form name="loginform" id="loginform" action="<?php echo esc_url(home_url('/login')); ?>" method="post">
            <label for="user_login">用户名或邮箱</label>
            <input type="text" name="log" id="user_login" autocomplete="username" required>
            <label for="user_pass">密码</label>
            <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
            <label class="login-remember" for="rememberme">
                <input name="rememberme" type="checkbox" id="rememberme" value="forever">
                <span>记住我</span>
            </label>
            <?php do_action('login_form'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
            <input type="hidden" name="testcookie" value="1">
            <p class="login-submit"><button type="submit">登录</button></p>
        </form>
        <div class="login-links">
            <a href="<?php echo esc_url(wp_lostpassword_url(home_url('/login'))); ?>" data-no-pjax>忘记密码？</a>
            <a href="<?php echo esc_url(home_url('/')); ?>">返回首页</a>
        </div>
    </div>
</main>

<?php get_footer(); ?>
