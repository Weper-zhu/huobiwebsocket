/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50553
Source Host           : localhost:3306
Source Database       : huobiwebsocket

Target Server Type    : MYSQL
Target Server Version : 50553
File Encoding         : 65001

Date: 2020-06-12 12:02:58
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for db_trade_config
-- ----------------------------
DROP TABLE IF EXISTS `db_trade_config`;
CREATE TABLE `db_trade_config` (
  `tc_key` varchar(50) NOT NULL COMMENT '键名',
  `tc_value` longtext NOT NULL COMMENT '值',
  `tc_des` varchar(250) DEFAULT NULL COMMENT '键值说明',
  PRIMARY KEY (`tc_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='合约参数设置表';

-- ----------------------------
-- Records of db_trade_config
-- ----------------------------
INSERT INTO `db_trade_config` VALUES ('trade_kline_huobi_url', 'ws://api.huobi.pro:443/ws', '交易对K线火币服务器WebSocket地址');
INSERT INTO `db_trade_config` VALUES ('trade_kline_server_url', 'ws.xxx.com/ws', '交易对K线正式服务器WebSocket地址-测试服使用');
INSERT INTO `db_trade_config` VALUES ('trade_kline_symbols', 'btcusdt,ethusdt,eosusdt,ltcusdt,etcusdt', '交易对K线火币交易对');
INSERT INTO `db_trade_config` VALUES ('trade_kline_websocket_url', 'wstest.xxx.com/ws', '交易对K线WebSocket地址-客户端使用');

-- ----------------------------
-- Table structure for db_trade_kline
-- ----------------------------
DROP TABLE IF EXISTS `db_trade_kline`;
CREATE TABLE `db_trade_kline` (
  `id` int(32) NOT NULL AUTO_INCREMENT COMMENT '交易表 交易表的id',
  `period` varchar(50) NOT NULL DEFAULT '0' COMMENT '时间粒度 1min, 5min, 15min, 30min, 60min, 4hour, 1day, 1mon, 1week, 1year',
  `symbol` varchar(50) NOT NULL DEFAULT '0' COMMENT '交易对',
  `open_price` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '开盘价格',
  `close_price` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '闭盘价格',
  `high_price` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '最高价格',
  `low_price` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '最低价格',
  `amount` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '成交量',
  `count` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '成交笔数',
  `vol` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '成交额, 即 sum(每一笔成交价 * 该笔的成交量)',
  `ch` varchar(100) CHARACTER SET utf32 NOT NULL DEFAULT '' COMMENT '交易对字符串',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '时间',
  `update_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `type` (`add_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='合约K线图数据';

-- ----------------------------
-- Records of db_trade_kline
-- ----------------------------
