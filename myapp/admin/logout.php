<?php
include __DIR__.'/common.php';
admin_logout();
redirect('login.php','已退出后台登录');
