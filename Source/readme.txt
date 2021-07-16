=== ECPay Payment for WooCommerce ===
Contributors: ecpaytechsupport
Tags: ecommerce, e-commerce, store, sales, sell, shop, cart, checkout, payment, ecpay
Requires at least: 4.5
Tested up to: 5.7.1
Requires PHP: 5.6 or later
Stable tag: 2.0.2106300
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

綠界科技金流外掛套件

== Description ==

綠界科技金流外掛套件，提供合作特店以及個人會員使用開放原始碼商店系統時，無須自行處理複雜的檢核，直接透過安裝設定外掛套件，便可以較快速的方式介接綠界科技的金流系統。

= 全方位金流 =
- 信用卡(一次付清、分期付款、定期定額)、ATM櫃員機、網路ATM、四大超商代碼/條碼，免註冊即可使用。
- 無需和個別銀行申請信用卡刷卡服務，只要註冊綠界科技即可享有多種收款方式。
- 可依需求設定要顯示給消費者單一或多種金流。

= 方便整合綠界科技物流服務、綠界科技電子發票服務 =
- 凡具備綠界科技會員資格即可免費申請。
- 全台7-ELEVEN、全家、萊爾富通路 皆可使用超商寄/取貨付款服務。
- 24H隨時查詢電子發票明細
- 提供電子發票管理及明細下載

= 更安全的付款方式 =
以簡單、安全且保障隱私的方式付款，符合國際PCI DSS 認證，保護每一個持卡人的交易安全，執行嚴謹的網路軟體硬體防護措施，加倍安心。

= 收款方式清單 =
- 信用卡(一次付清、分期付款、定期定額)
- 網路ATM
- ATM櫃員機
- 超商代碼
- 超商條碼
- Apple Pay

= 注意事項 =
- 1.若須同時使用綠界科技WooCommerce物流模組，除了更新綠界科技WooCommerce金流模組外，綠界科技WooCommerce物流模組也請同步更新才能正常使用。
- 2.本模組訂單扣庫存數量是在付款完成後才進行扣除，所以如果付款方式為非即時完成，例如：超商代碼、ATM，庫存會於消費者實際繳款後才扣除。限量商品請避免使用非即時金流收款。

= 聯絡我們 =
  綠界技術服務工程師信箱: techsupport@ecpay.com.tw


== Installation ==

= 系統需求 =

- PHP version 5.6.11 or greater
- MySQL version 5.5 or greater

= 自動安裝 =
1. 登入至您的 WordPress dashboard，拜訪 "Plugins menu" 並點擊 "Add"。
2. 在"search field"中輸入"ECPay Payment for WooCommerce"，然後點擊搜尋。
3. 點擊 "安裝" 即可進行安裝。

= 手動安裝 =
詳細說明請參閱 [綠界科技金流外掛套件安裝導引文件](https://github.com/ECPay/WooCommerce_Payment)。

== Frequently Asked Questions ==

== Changelog ==

v2.0.2106300
修正相容性至 WordPress Version 5.7.1
修正相容性至 WooCommerce Version 5.3.0

v2.0.2104150
修正部份情境下定期定額訂單無法更新授權結果問題

v2.0.2010210
修正定期定額訂單不會自動開立發票問題

v2.0.2009210
調整結帳流程
調整 chrome samesite 屬性相容性
移除後台設定欄位「測試模式」

v1.2.2007070
修正相容性至 WordPress Version 5.4.2
修正相容性至 WooCommerce Version 4.2.2

v1.2.2003020
調整 cURL timeout

v1.2.2002200
更新 Applepay SDK
更新金流 SDK
修正無效訂單狀態更新問題

v1.2.2001070
移除付款方式 GooglePay
修正定期定額訂單不會自動開立發票問題

v1.2.1912060
調整版號規則
修正 CVS 取號結果通知
修正 Helper

v1.2.191004
修正壓碼問題

v1.1.180911
修正電子發票無法自動開立的問題

v1.1.180313
1.調整金流成功交易，無法返回感謝頁，造成GA無法偵測問題，
2.調整get_return_url 問題。

v1.1.1201
調整信用卡定期定額設定

v1.1.1124
金流優化 ,ATM ＆CVS 取號結果通知，同步到使用者會員中心訂單中

v1.1.1115
定期定額後台功能 新增 「新增/刪除功能」

v1.1.0911
電子發票開立備註欄增加信用卡卡號後4碼,需搭配https://github.com/ECPay/WooCommerce_Invoice V1.1.0911使用

v1.1.0901
Official release