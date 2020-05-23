# gopherserversmf
Gopher Server for the SMF Forum Software - SMF 2.0.x and I belive SMF 2.1 is supported as well in PHP using socket
What is it?
This is an example gopher server using the gopher protocol written in PHP using sockets. By default listens on the Gopher port 70

Tested on PHP 7.3

To install:
Modify - gopherserversmf.php and change the constants near the top of the file
In windows in the php.in you must enable the following extension: php_sockets.dll
Then you should run this as a cron or scheduled task. This is a server so it needs to always be running.

To test with: Use a Gopher Client such as Gopher Browser for Windows http://www.jaruzel.com/gopher/gopher-client-browser-for-windows/


Licensed under MIT
