# SOL Custom Functions

スイッチオンラボ WordPress サイト向けカスタム機能プラグイン。

- **Plugin Name:** SOL Custom Functions
- **Version:** 1.0
- **Author:** SwitchonLab
- **依存プラグイン:** LifterLMS、LifterLMS MailChimp

---

## 機能一覧

### 1. 入会時のアンケート＆CSVエクスポート

新規ユーザー登録フォーム（LifterLMS）に「入会のきっかけ」と「年代」の選択欄を追加します。

- 登録時に回答をユーザーメタ（`enrollment_source`・`sol_age_group`）へ保存
- 管理画面のユーザープロフィール画面で回答を表示・管理者が編集可能
- **管理メニュー：** ユーザー → アンケートエクスポート
  - 全ユーザーの回答を BOM 付き UTF-8 CSV でダウンロード

---

### 1.5. 有料会員登録時に無料会員へ自動同時登録

有料メンバーシップ（ID: `2656`）に登録したユーザーを、無料メンバーシップ（ID: `2646`）にも自動的に登録します。

> メンバーシップ ID を変更する場合は関数 `sol_auto_enroll_free_membership` 内の定数を更新してください。

---

### 2. MailChimp 自動連携

LifterLMS MailChimp 連携において、メールマガジン購読チェックボックスを常に「購読中（subscribed）」として扱います。

- フィルター `llms_mc_is_subscribed_default` → `true` で固定
- フィルター `llms_mc_get_subscriber_status` → `'subscribed'` で固定

以前 CSS で非表示にしていたチェックボックスの代替処理です。

---

### 3. ナビゲーター・レッスン完了演出

レッスン完了時にキャラクターアイコンと応援メッセージをトースト表示します。コース修了時はコンフェッティ（紙吹雪）アニメーションも再生されます。

**ナビゲーターキャラクター（4 種）**

| キー | 名前 | カラー |
|------|------|--------|
| `akira` | 神子上洸 | `#aaaaaa` |
| `asuka` | 三貫地明日架 | `#FF6B00` |
| `yomogi` | 雛森ヨモギ | `#00BFFF` |
| `nene` | 石動音々 | `#8B5CF6` |

**コース編集画面での設定方法**

1. 管理画面でコース編集ページを開く
2. サイドバーの「SOL Navigator」メタボックスからキャラクターを選択
3. 顔写真URLを入力（省略時はコース講師のアバターを使用）

---

### 4. マイコース ブックマーク機能

マイページの「マイコース」一覧でコースをブックマーク（お気に入り）登録し、上位に優先表示します。

- ★ボタンでブックマークの追加・解除（Ajax で即時反映）
- ブックマーク済みコースをダッシュボード上部に最大4件表示
- ダッシュボードのコース一覧を4列グリッドに変更
- ブックマーク情報はユーザーメタ `sol_bookmarked_courses` に保存

---

### 5. ダッシュボード お知らせティッカー

LifterLMS ダッシュボードのトップに、カスタム投稿タイプ「ニュース」の最新5件を横スクロールするティッカー形式で表示します。

**設定**

```php
// ニュース投稿タイプのスラッグ（CPT UI で設定した値に合わせてください）
define( 'SOL_NEWS_POST_TYPE', 'news' );
```

- ホバーでスクロール一時停止
- `prefers-reduced-motion` 対応
- 「一覧 →」リンクでアーカイブページへ遷移

---

### 6. AIチャットボット「明日架」

全ページの右下に固定表示されるフローティングチャットボット。Dify API を経由してスイッチオンラボのナビゲーターキャラクター「三貫地明日架」として応答します。

- Dify API キーはサーバー側のみで使用（フロントエンドに露出しない）
- 会話履歴はセッションストレージに保持
- マークダウン（**太字**、改行）に対応

**設定定数（`wp-config.php` または本ファイル内で定義）**

```php
define( 'SOL_ASUKA_AVATAR_URL', 'https://example.com/asuka.png' );
define( 'SOL_DIFY_API_KEY',     'app-xxxxxxxxxxxx' );
define( 'SOL_DIFY_API_URL',     'https://api.dify.ai/v1' );
```

---

### 7. Scratch チュートリアル iframe ショートコード

Vercel 上にホストされた Scratch チュートリアルビューワーを、HMAC-SHA256 トークン認証付きの iframe として埋め込みます。

**使用方法**

```
[switchonlab_scratch project="プロジェクト名"]
```

- トークンは発行から **1時間** 有効
- シークレットキーは `wp-config.php` に定義

```php
define( 'SOL_TOKEN_SECRET', 'your-secret-key' );
```

---

### 8. WordPress 7 対応：ショートコード強制実行

WordPress 7 以降で `the_content` フィルターにショートコードが自動適用されなくなった場合に備え、`do_shortcode` を明示的に登録します。

```php
add_filter( 'the_content', 'do_shortcode' );
```

---

## インストール

1. このリポジトリを WordPress の `wp-content/plugins/sol-custom-function/` に配置
2. 管理画面 → プラグイン → 「SOL Custom Functions」を有効化
3. 必要な定数を `wp-config.php` に追加（機能 6・7 を使用する場合）

## 注意事項

- LifterLMS がインストール・有効化されていることが前提です
- `SOL_DIFY_API_KEY` は絶対に公開リポジトリにコミットしないでください
- メンバーシップ ID（1.5）は環境に合わせて変更してください
