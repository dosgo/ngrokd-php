# ngrokd-php 

一个简单的ngrokd服务器，使用php写的，需要swoole扩展才行，而且编译swoole扩展的时候必须，加上openssl参数，不然没法使用，可以用来看ngrokd的原理。不推荐部署，性能堪忧，正在完善中。



目前的问题

1.  不支持TCP映射，只支持http or https
2.  目前只能开一个进程，否则变量无法同步，导致不可预料的问题。
3.  大网页很慢。。。
4.  代码有些乱第一个swoole项目。
5.  不支持验证用户。
6.  不支持Hostname全域名参数。



至于怎么运行嘛，安装好php环境，跟swoole扩展，直接php ngrokd.php就可以了。。。,记得，修改$baseurl改成你泛域名噢，还有$sslinfo改成你的ssl证书路径。
