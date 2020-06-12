首先感谢 https://github.com/ztg1/huobiapia 提供的思路，这个版本是根据里面代码按照实际项目需求修改而来的.

火币K线数据官方文档：https://huobiapi.github.io/docs/spot/v1/cn/#k，WebSocket版：https://huobiapi.github.io/docs/spot/v1/cn/#5ea2e0cde2-2

第一种方法-POST请求：
   
   启动文件：TradeKline.php
   
   代码文件：application/cli/controller/TradeKline.php
   
   启动命令：
   
    linux：cd /www/huobiwebsocket & php TradeKline.php start -d，-d代表以daemon（守护进程）方式启动
    widows：E:\phpStudy\PHPTutorial\php\php-7.2.1-nts\php.exe E:\WWW\huobiwebsocket\TradeKline.php start
   
   停止命令：
   
    linux：cd /www/huobiwebsocket & php TradeKline.php stop
    widows：E:\phpStudy\PHPTutorial\php\php-7.2.1-nts\php.exe E:\WWW\huobiwebsocket\TradeKline.php stop
    
   **重要的事情**：运行这种方法最好把服务器放到外网，或者把public目录下的post.php文件部署到外网服务器代理请求火币api
    
第二种方法-WebSocket订阅：

   这种方法主要是把服务器当成一个WebSocket Client连接到火币的WebSocket服务器，订阅交易对的不同时间粒度的K线数据，成功之后火币服务器就会在K线数据变化的时候主动推送消息给到服务器，接收到推送之后就可以进行存储等操作，同时服务器也把接收到的推送消息再推送给连接到服务端的WebSocket Client。
   
   启动文件：TradeKline.php
      
   代码文件：application/cli/controller/TradeKline.php
   
   启动和停止参考第一种方法
   
   **重要的事情**：运行这种方法最好把服务器放到外网；因为这个方法启动的Worker同时也是一个WebSocket Server，所以可以布置一台服务器到外网，其他国内服务器就可以作为WebSocket Client连接到外网服务器获取K线数据了（有点绕）；