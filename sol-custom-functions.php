<?php
/**
 * Plugin Name: SOL Custom Functions
 * Description: スイッチオンラボ カスタム機能
 * Version: 1.0
 * Author: SwitchonLab
 * ＜機能リスト＞
 * 1)入会時のアンケート
 * 1.5)有料会員登録時に無料会員に同時登録
 * 2)MailChimp自動連携
 * 3)ナビゲーター・レッスン完了演出機能
 * 4)マイコース ブックマーク機能
 * 5)ダッシュボード お知らせティッカー
 * 6) AIチャットボット「明日架」
 * 7） Scratchチュートリアル iframeショートコード（トークン認証付き）
 * 8) WordPress 7対応：ショートコード強制実行
 */


/**
 * ============================================================
 * 1. 入会時のアンケート＆エクスポート機能
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function sol_get_enrollment_source_options(): array {
    return array(
        'threads'    => 'Threadsを見て',
        'x'          => 'X (旧Twitter)を見て',
        'youtube'    => 'YouTubeを見て',
        'note'       => 'noteの記事を読んで',
        'tiktok'     => 'TikTokを見て',
        'coeteco'    => 'コエテコを見て',
        'web_search' => 'Web検索（Google/Yahoo等）',
        'ai_search'  => 'AIに聞いて（ChatGPT/Perplexity等）',
        'friend'     => '知人の紹介',
        'other'      => 'その他',
    );
}

function sol_get_age_group_options(): array {
    return array(
        'under10' => '10代以下',
        '10s'     => '10代',
        '20s'     => '20代',
        '30s'     => '30代',
        '40s'     => '40代',
        '50s'     => '50代',
        '60plus'  => '60代以上',
    );
}

add_filter( 'lifterlms_get_person_fields', function( $fields ) {
    $source_options = array( '' => '選択してください' ) + sol_get_enrollment_source_options();
    $fields[] = array(
        'type'     => 'select',
        'id'       => 'enrollment_source',
        'label'    => '入会のきっかけを教えてください',
        'required' => true,
        'options'  => $source_options,
    );

    $age_options = array( '' => '選択してください' ) + sol_get_age_group_options();
    $fields[] = array(
        'type'     => 'select',
        'id'       => 'sol_age_group',
        'label'    => '受講される方の年代を教えてください（※保護者の方が登録される場合は、お子さまの年代をお選びください）',
        'required' => true,
        'options'  => $age_options,
    );

    return $fields;
} );

add_action( 'show_user_profile', 'sol_display_enrollment_survey_on_profile' );
add_action( 'edit_user_profile', 'sol_display_enrollment_survey_on_profile' );

function sol_display_enrollment_survey_on_profile( WP_User $user ): void {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) return;

    $source         = get_user_meta( $user->ID, 'enrollment_source', true );
    $source_options = sol_get_enrollment_source_options();
    $source_display = $source_options[ $source ] ?? ( $source ?: '未回答' );

    $age_group      = get_user_meta( $user->ID, 'sol_age_group', true );
    $age_options    = sol_get_age_group_options();
    $age_display    = $age_options[ $age_group ] ?? ( $age_group ?: '未回答' );
    ?>
    <hr>
    <h3>スクール入会アンケート結果</h3>
    <table class="form-table">
        <?php wp_nonce_field( 'sol_save_enrollment_survey_' . $user->ID, 'sol_enrollment_nonce' ); ?>
        <tr>
            <th><label for="enrollment_source">入会のきっかけ</label></th>
            <td>
                <?php if ( current_user_can( 'administrator' ) ) : ?>
                    <select name="enrollment_source" id="enrollment_source">
                        <option value="">未回答</option>
                        <?php foreach ( $source_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $source, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <strong><?php echo esc_html( $source_display ); ?></strong>
                <?php endif; ?>
                <p class="description">※新規登録時にユーザーが選択した内容です。</p>
            </td>
        </tr>
        <tr>
            <th><label for="sol_age_group">年代</label></th>
            <td>
                <?php if ( current_user_can( 'administrator' ) ) : ?>
                    <select name="sol_age_group" id="sol_age_group">
                        <option value="">未回答</option>
                        <?php foreach ( $age_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $age_group, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <strong><?php echo esc_html( $age_display ); ?></strong>
                <?php endif; ?>
                <p class="description">※新規登録時にユーザーが選択した内容です。保護者が登録した場合はお子さまの年代です。</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update',  'sol_save_enrollment_survey' );
add_action( 'edit_user_profile_update', 'sol_save_enrollment_survey' );

function sol_save_enrollment_survey( int $user_id ): void {
    if (
        ! current_user_can( 'administrator' ) ||
        ! isset( $_POST['sol_enrollment_nonce'] ) ||
        ! wp_verify_nonce( $_POST['sol_enrollment_nonce'], 'sol_save_enrollment_survey_' . $user_id )
    ) return;

    // 入会のきっかけ
    $allowed_sources = array_keys( sol_get_enrollment_source_options() );
    $source_value    = sanitize_text_field( $_POST['enrollment_source'] ?? '' );
    if ( $source_value === '' || in_array( $source_value, $allowed_sources, true ) ) {
        update_user_meta( $user_id, 'enrollment_source', $source_value );
    }

    // 年代
    $allowed_ages = array_keys( sol_get_age_group_options() );
    $age_value    = sanitize_text_field( $_POST['sol_age_group'] ?? '' );
    if ( $age_value === '' || in_array( $age_value, $allowed_ages, true ) ) {
        update_user_meta( $user_id, 'sol_age_group', $age_value );
    }
}

add_action( 'admin_menu', function() {
    add_submenu_page(
        'users.php',
        'アンケート結果エクスポート',
        'アンケートエクスポート',
        'administrator',
        'sol-export-survey',
        'sol_render_export_page'
    );
} );

add_action( 'admin_init', function() {
    if (
        ! isset( $_POST['sol_export_csv'] ) ||
        ! isset( $_POST['sol_export_nonce'] ) ||
        ! wp_verify_nonce( $_POST['sol_export_nonce'], 'sol_export_csv_action' ) ||
        ! current_user_can( 'administrator' )
    ) return;

    sol_export_survey_csv();
    exit;
} );

function sol_render_export_page(): void {
    ?>
    <div class="wrap">
        <h1>入会アンケート結果 CSVエクスポート</h1>
        <p>全ユーザーの「入会のきっかけ」「年代」アンケート結果をCSVでダウンロードできます。</p>
        <form method="post">
            <?php wp_nonce_field( 'sol_export_csv_action', 'sol_export_nonce' ); ?>
            <input type="submit" name="sol_export_csv" class="button button-primary" value="CSVをダウンロード">
        </form>
    </div>
    <?php
}

function sol_export_survey_csv(): void {
    $source_options = sol_get_enrollment_source_options();
    $age_options    = sol_get_age_group_options();
    $users          = get_users( array( 'fields' => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ) ) );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="enrollment_survey_' . date('Ymd') . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );
    fwrite( $output, "\xEF\xBB\xBF" );

    fputcsv( $output, array( 'ユーザーID', 'ユーザー名', 'メールアドレス', '表示名', '登録日', '入会のきっかけ', '年代' ) );

    foreach ( $users as $user ) {
        $source      = get_user_meta( $user->ID, 'enrollment_source', true );
        $source_disp = $source_options[ $source ] ?? ( $source ? $source : '未回答' );

        $age_group   = get_user_meta( $user->ID, 'sol_age_group', true );
        $age_disp    = $age_options[ $age_group ] ?? ( $age_group ? $age_group : '未回答' );

        fputcsv( $output, array(
            $user->ID,
            $user->user_login,
            $user->user_email,
            $user->display_name,
            $user->user_registered,
            $source_disp,
            $age_disp,
        ) );
    }

    fclose( $output );
}

/**
 * ============================================================
 * 1.5 
 * 有料会員に登録した人が自動的に無料会員にも登録
 * ============================================================
 */

add_action( 'llms_user_enrolled_in_membership', 'sol_auto_enroll_free_membership', 10, 2 );

function sol_auto_enroll_free_membership( int $student_id, int $membership_id ): void {
    $paid_membership_id = 2656;
    $free_membership_id = 2646;

    if ( $membership_id !== $paid_membership_id ) return;

    if ( ! llms_is_user_enrolled( $student_id, $free_membership_id ) ) {
        llms_enroll_student( $student_id, $free_membership_id );
    }
}



/**
 * ============================================================
 * 2. MailChimp　自動連携
 * LifterLMS Mailchimp 連携：チェックボックスを常に「ON」として扱う
 * 以前CSSで非表示にしたチェックボックスの代わりに、システム側で購読フラグを立てます
 * ============================================================
 */
add_filter( 'llms_mc_is_subscribed_default', '__return_true' );
add_filter( 'llms_mc_get_subscriber_status', function( $status ) {
    return 'subscribed'; // ステータスを「Subscribed（購読中）」で固定して送信
});



/**
 * ============================================================
 * 3. ナビゲーター・レッスン完了演出機能
 * ============================================================
 */

function sol_get_navigator_data(): array {
    return array(
        'akira' => array(
            'name'             => '神子上洸',
            'color'            => '#aaaaaa',
            'title'            => 'やったね！',
            'message'          => 'レッスン完了だよ<br>この調子で進んでいこう！',
            'complete_title'   => 'コース修了おめでとう！',
            'complete_message' => '全レッスン完了だよ！<br>本当によく頑張ったね！',
        ),
        'asuka' => array(
            'name'             => '三貫地明日架',
            'color'            => '#FF6B00',
            'title'            => 'さすがなのです！',
            'message'          => 'レッスン完了<br>次もこの調子で進むのです！',
            'complete_title'   => 'コース修了おめでとうなのです！',
            'complete_message' => '全レッスン完了なのです！<br>本当によく頑張ったのです！',
        ),
        'yomogi' => array(
            'name'             => '雛森ヨモギ',
            'color'            => '#00BFFF',
            'title'            => 'やったー！',
            'message'          => 'レッスン完了なのん<br>次もこの調子で進むのん',
            'complete_title'   => 'コース修了おめでとうなのん！',
            'complete_message' => '全レッスン完了なのん！<br>本当によく頑張ったのん！',
        ),
        'nene' => array(
            'name'             => '石動音々',
            'color'            => '#8B5CF6',
            'title'            => 'やったー！',
            'message'          => 'レッスン完了でござる<br>次もこの調子でござるよ',
            'complete_title'   => 'コース修了おめでとうでござる！',
            'complete_message' => '全レッスン完了でござる！<br>本当によく頑張ったでござるよ！',
        ),
    );
}

/**
 * コース編集画面にナビゲーター設定欄を追加
 */
add_action( 'add_meta_boxes', 'sol_add_navigator_metabox' );
function sol_add_navigator_metabox() {
    add_meta_box(
        'sol_navigator_box',
        'SOL Navigator',
        'sol_navigator_metabox_html',
        'course',
        'side'
    );
}

function sol_navigator_metabox_html( $post ) {
    $navigators   = sol_get_navigator_data();
    $selected_key = get_post_meta( $post->ID, 'sol_navigator_key', true );
    $photo        = get_post_meta( $post->ID, 'sol_navigator_photo', true );
    wp_nonce_field( 'sol_navigator_save', 'sol_navigator_nonce' );

    echo '<p><label>ナビゲーターを選択</label><br>';
    echo '<select name="sol_navigator_key" style="width:100%">';
    echo '<option value="">-- 選択してください --</option>';
    foreach ( $navigators as $key => $data ) {
        $sel = selected( $selected_key, $key, false );
        echo '<option value="' . esc_attr( $key ) . '"' . $sel . '>' . esc_html( $data['name'] ) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label>顔写真URL（メディアライブラリからコピー）</label><br>';
    echo '<input type="url" name="sol_navigator_photo" value="' . esc_attr( $photo ) . '" style="width:100%"></p>';
    if ( $photo ) {
        echo '<img src="' . esc_url( $photo ) . '" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">';
    }
}

add_action( 'save_post_course', 'sol_save_navigator_meta' );
function sol_save_navigator_meta( $post_id ) {
    if ( ! isset( $_POST['sol_navigator_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['sol_navigator_nonce'], 'sol_navigator_save' ) ) return;
    if ( isset( $_POST['sol_navigator_key'] ) ) {
        update_post_meta( $post_id, 'sol_navigator_key', sanitize_text_field( $_POST['sol_navigator_key'] ) );
    }
    if ( isset( $_POST['sol_navigator_photo'] ) ) {
        update_post_meta( $post_id, 'sol_navigator_photo', esc_url_raw( $_POST['sol_navigator_photo'] ) );
    }
}

/**
 * レッスンページにナビゲーター情報を渡す
 */
add_action( 'wp_enqueue_scripts', 'sol_enqueue_lesson_complete_custom' );
function sol_enqueue_lesson_complete_custom() {
    if ( ! is_singular( 'lesson' ) ) return;

    $lesson_id = get_the_ID();
    $course_id = llms_get_post( $lesson_id )->get( 'parent_course' );
    if ( ! $course_id ) return;

    $nav_key    = get_post_meta( $course_id, 'sol_navigator_key', true );
    $avatar_url = get_post_meta( $course_id, 'sol_navigator_photo', true );
    $navigators = sol_get_navigator_data();

    $nav_data = isset( $navigators[ $nav_key ] ) ? $navigators[ $nav_key ] : array(
        'name'             => '神子上洸',
        'color'            => '#1ab3b3',
        'title'            => 'やったね！',
        'message'          => 'レッスン完了です！<br>この調子で進みましょう！',
        'complete_title'   => 'コース修了おめでとう！',
        'complete_message' => '全レッスン完了だよ！<br>本当によく頑張ったね！',
    );

    if ( ! $avatar_url ) {
        $course      = llms_get_post( $course_id );
        $instructors = $course->get_instructors();
        if ( ! empty( $instructors ) ) {
            $avatar_url = get_avatar_url( $instructors[0]['id'], array( 'size' => 80 ) );
        }
    }

    // 最後のレッスンか判定
    $is_last_lesson = false;
    $student = llms_get_student( get_current_user_id() );
    if ( $student ) {
        $course_obj  = llms_get_post( $course_id );
        $all_lessons = $course_obj->get_lessons( 'ids' );
        $completed   = 0;
        foreach ( $all_lessons as $lid ) {
            if ( $student->is_complete( $lid, 'lesson' ) ) {
                $completed++;
            }
        }
        $is_last_lesson = ( count( $all_lessons ) - $completed === 1 );
    }

    $data = array(
        'avatarUrl'       => esc_url( $avatar_url ),
        'name'            => esc_html( $nav_data['name'] ),
        'color'           => esc_attr( $nav_data['color'] ),
        'title'           => $nav_data['title'],
        'message'         => $nav_data['message'],
        'completeTitle'   => $nav_data['complete_title'],
        'completeMessage' => $nav_data['complete_message'],
        'isLastLesson'    => $is_last_lesson,
    );

    add_action( 'wp_head', function() use ( $data ) {
        echo '<script>var solNavigator = ' . wp_json_encode( $data ) . ';</script>';
        echo '<style>' . sol_lesson_complete_css() . '</style>';
    });
    add_action( 'wp_footer', function() {
        echo '<script>' . sol_lesson_complete_js() . '</script>';
    });
}

function sol_lesson_complete_css() {
    return '.sol-complete-toast {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -60px);
    z-index: 99999;
    background: #fff;
    border-radius: 30px;
    box-shadow: 0 16px 60px rgba(0,0,0,.28);
    padding: 48px 54px;
    width: 630px;
    display: flex;
    align-items: center;
    gap: 30px;
    opacity: 0;
    transition: opacity .4s ease, transform .4s ease;
    border-top: 9px solid #1ab3b3;
}
.sol-complete-toast.sol-show {
    opacity: 1;
    transform: translate(-50%, -50%);
}
.sol-complete-toast.sol-hide {
    opacity: 0;
    transform: translate(-50%, -60px);
}
.sol-toast-avatar { flex-shrink: 0; }
.sol-toast-title {
    font-weight: 700;
    font-size: 36px;
    margin-bottom: 12px;
}
.sol-toast-message {
    font-size: 22px;
    color: #555;
    line-height: 1.7;
    margin-bottom: 12px;
}
.sol-toast-nav-name { font-size: 19px; color: #999; }';
}

function sol_lesson_complete_js() {
    return '
window.showSolToast = function( isCoursComplete ) {
    var nav = (typeof solNavigator !== "undefined") ? solNavigator : {};
    var avatarUrl = nav.avatarUrl || "";
    var navName   = nav.name    || "Navigator";
    var navColor  = nav.color   || "#1ab3b3";
    var navTitle  = isCoursComplete
        ? ( nav.completeTitle   || "コース修了おめでとう！" )
        : ( nav.title           || "やったね！" );
    var navMsg    = isCoursComplete
        ? ( nav.completeMessage || "全レッスン完了！本当によく頑張ったね！" )
        : ( nav.message         || "レッスン完了です！" );

    var avatarHtml = avatarUrl
        ? "<img src=\"" + avatarUrl + "\" alt=\"" + navName + "\" style=\"width:120px;height:120px;border-radius:50%;object-fit:cover;border:6px solid " + navColor + ";\">"
        : "<div style=\"width:120px;height:120px;border-radius:50%;background:" + navColor + ";color:#fff;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:bold;\">" + navName.charAt(0) + "</div>";

    var toast = document.createElement("div");
    toast.className = "sol-complete-toast";
    toast.style.borderTopColor = navColor;
    toast.innerHTML =
        "<div class=\"sol-toast-avatar\">" + avatarHtml + "</div>" +
        "<div class=\"sol-toast-body\">" +
            "<div class=\"sol-toast-title\" style=\"color:" + navColor + ";\">" + navTitle + "</div>" +
            "<div class=\"sol-toast-message\">" + navMsg + "</div>" +
            "<div class=\"sol-toast-nav-name\">by " + navName + "</div>" +
        "</div>";
    document.body.appendChild(toast);
    requestAnimationFrame(function(){
        requestAnimationFrame(function(){ toast.classList.add("sol-show"); });
    });

    var duration = isCoursComplete ? 10000 : 4000;
    setTimeout(function(){
        toast.classList.remove("sol-show");
        toast.classList.add("sol-hide");
        setTimeout(function(){ toast.remove(); }, 400);
    }, duration);

    if ( isCoursComplete ) {
        solStartConfetti();
    }
};

window.solStartConfetti = function() {
    var canvas = document.createElement("canvas");
    canvas.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;z-index:99998;pointer-events:none;";
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);
    var ctx = canvas.getContext("2d");

    var colors = ["#1ab3b3","#FF6B00","#8B5CF6","#00BFFF","#FFD700","#FF69B4","#7CFC00","#FF4500"];
    var pieces = [];
    for (var i = 0; i < 180; i++) {
        pieces.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            w: Math.random() * 12 + 6,
            h: Math.random() * 6 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            speed: Math.random() * 4 + 2,
            angle: Math.random() * 360,
            spin: (Math.random() - 0.5) * 6,
            swing: Math.random() * 3,
            swingSpeed: Math.random() * 0.05 + 0.02,
            swingAngle: Math.random() * Math.PI * 2
        });
    }

    var start = null;
    var duration = 10000;

    function draw(timestamp) {
        if (!start) start = timestamp;
        var elapsed = timestamp - start;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        var alpha = elapsed > duration - 1000 ? Math.max(0, (duration - elapsed) / 1000) : 1;
        ctx.globalAlpha = alpha;
        pieces.forEach(function(p) {
            p.y += p.speed;
            p.angle += p.spin;
            p.swingAngle += p.swingSpeed;
            p.x += Math.sin(p.swingAngle) * p.swing;
            if (p.y > canvas.height) { p.y = -20; p.x = Math.random() * canvas.width; }
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.angle * Math.PI / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
            ctx.restore();
        });
        if (elapsed < duration) { requestAnimationFrame(draw); } else { canvas.remove(); }
    }
    requestAnimationFrame(draw);
};

(function(){
    var buttons = document.querySelectorAll(".llms-complete-lesson-form button[type=\'submit\']");
    if (!buttons.length) return;
    var forms = document.querySelectorAll(".llms-complete-lesson-form");
    if (!forms.length) return;
    var isLast = (typeof solNavigator !== "undefined") && solNavigator.isLastLesson;

    if (isLast) {
        var submitted = false;
        Array.prototype.forEach.call(forms, function(form){
            form.addEventListener("submit", function(e){
                if (submitted) return;
                e.preventDefault();
                window.showSolToast(true);
                setTimeout(function(){
                    submitted = true;
                    buttons[0].click();
                }, 7000);
            });
        });
    } else {
        Array.prototype.forEach.call(buttons, function(btn){
            btn.addEventListener("click", function(){
                setTimeout(function(){
                    window.showSolToast(false);
                }, 800);
            });
        });
    }
})();
';
}


/**
 * ============================================================
 * 4 マイコース ブックマーク機能
 * ============================================================
 *マイページ上で「マイコース」をブックマークして上位表示する
 */


// -------------------------------------------------------
// 1. CSS & JavaScript の読み込み
// -------------------------------------------------------
function sol_bookmark_assets() {

    if ( ! is_user_logged_in() ) return;

    $user_id   = get_current_user_id();
    $bookmarks = get_user_meta( $user_id, 'sol_bookmarked_courses', true );
    if ( ! is_array( $bookmarks ) ) {
        $bookmarks = [];
    }

    $css = '
        .sol-bm-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.90);
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.18);
            padding: 0;
        }
        .sol-bm-btn:hover { transform: scale(1.15); }
        .sol-bm-btn.is-bookmarked {
            background: #FFF3E0;
            color: #E65100;
        }
        li.llms-loop-item { position: relative; }
        li.llms-loop-item.sol-is-bookmarked {
            outline: 2px solid #FF9800;
            border-radius: 6px;
        }
        li.sol-bm-section-label {
            list-style: none;
            width: 100%;
            font-size: 12px;
            font-weight: bold;
            color: #E65100;
            padding: 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            grid-column: 1 / -1;
        }
        li.sol-bm-section-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #FFCC80;
        }
        li.sol-bm-divider {
            list-style: none;
            width: 100%;
            font-size: 12px;
            color: #aaa;
            padding: 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            grid-column: 1 / -1;
        }
        li.sol-bm-divider::before,
        li.sol-bm-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }
        .sol-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            padding: 9px 22px;
            border-radius: 24px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 99999;
            pointer-events: none;
            white-space: nowrap;
        }
        .sol-toast.show { opacity: 1; }

        /* ダッシュボードのマイコースを4列グリッドに */
        .llms-student-dashboard[data-current="dashboard"]
        .llms-sd-section.llms-my-courses .llms-loop-list,
        .llms-student-dashboard[data-current="view-courses"]
        .llms-loop-list {
            grid-template-columns: repeat(4, 1fr) !important;
        }

        /* ダッシュボード上の全カードのリンク色・下線をリセット */
        .llms-student-dashboard[data-current="dashboard"]
        .llms-my-courses .llms-loop-item .llms-loop-link {
            color: inherit;
            text-decoration: none;
        }
        .llms-student-dashboard[data-current="dashboard"]
        .llms-my-courses .llms-loop-item .llms-loop-title {
            font-size: 1em;
            font-weight: 700;
            color: #333;
            text-decoration: none;
        }
        .llms-student-dashboard[data-current="dashboard"]
        .llms-my-courses .llms-loop-item .llms-loop-item-footer,
        .llms-student-dashboard[data-current="dashboard"]
        .llms-my-courses .llms-loop-item .llms-loop-item-footer * {
            color: #333;
            text-decoration: none;
        }
    ';

    wp_register_style( 'sol-bookmark', false );
    wp_enqueue_style( 'sol-bookmark' );
    wp_add_inline_style( 'sol-bookmark', $css );

    $js_vars = sprintf(
        'var solBookmark = %s;',
        wp_json_encode( [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'sol_bookmark_nonce' ),
            'bookmarks' => array_map( 'intval', $bookmarks ),
        ] )
    );

    $js = '
document.addEventListener("DOMContentLoaded", function () {

    var section = document.querySelector( ".llms-sd-section.llms-my-courses" );
    if ( ! section ) return;

    var ul = section.querySelector( ".llms-loop-list" );
    if ( ! ul ) return;

    var isDashboard = !! document.querySelector(
        ".llms-student-dashboard[data-current=\"dashboard\"]"
    );

    // ---- ヘルパー関数 ----

    function getItems() {
        return Array.from( ul.querySelectorAll( ":scope > li.llms-loop-item" ) );
    }

    function getCourseId( el ) {
        var match = el.className.match( /\bpost-(\d+)\b/ );
        return match ? parseInt( match[1], 10 ) : 0;
    }

    function makeSeparator( text, className ) {
        var el = document.createElement( "li" );
        el.className   = className + " sol-separator";
        el.textContent = text;
        return el;
    }

    // トースト
    var toast = document.createElement( "div" );
    toast.className = "sol-toast";
    document.body.appendChild( toast );
    function showToast( msg ) {
        toast.textContent = msg;
        toast.classList.add( "show" );
        clearTimeout( window._solToastTimer );
        window._solToastTimer = setTimeout( function () {
            toast.classList.remove( "show" );
        }, 2600 );
    }

    // ---- DOMにないブックマーク済みコースをAJAX取得して注入 ----
    function injectMissingBookmarks( callback ) {
        if ( ! isDashboard ) { callback(); return; }

        var domIds  = getItems().map( getCourseId );
        var missing = solBookmark.bookmarks.filter( function ( id ) {
            return domIds.indexOf( id ) === -1;
        } ).slice( 0, 4 );

        if ( missing.length === 0 ) { callback(); return; }

        fetch( solBookmark.ajaxUrl, {
            method  : "POST",
            headers : { "Content-Type": "application/x-www-form-urlencoded" },
            body    : new URLSearchParams( {
                action    : "sol_get_course_cards",
                course_ids: missing.join( "," ),
                nonce     : solBookmark.nonce,
            } ).toString(),
        } )
        .then( function ( r ) { return r.json(); } )
        .then( function ( res ) {
            if ( ! res.success ) { callback(); return; }

            res.data.courses.forEach( function ( course ) {
                var li = document.createElement( "li" );
                li.className = "llms-loop-item post-" + course.id
                    + " course type-course status-publish sol-injected";

                var thumb = course.thumbnail
                    ? "<img src=\"" + course.thumbnail + "\" class=\"llms-featured-image wp-post-image\" style=\"width:100%;display:block;\">"
                    : "<div style=\"width:100%;height:130px;background:#e0e0e0;\"></div>";

                var avatarHtml = course.instructor_avatar
                    ? "<img src=\"" + course.instructor_avatar + "\" class=\"avatar avatar-28 photo\" width=\"28\" height=\"28\">"
                    : "";

                var authorHtml = course.instructor_name
                    ? "<div class=\"llms-author\">" + avatarHtml + "<span class=\"llms-author-info name\">" + course.instructor_name + "</span></div>"
                    : "";

                var lengthHtml = course.length
                    ? "<div class=\"llms-meta llms-length\"><p>予定時間: " + course.length + "</p></div>"
                    : "";

                var difficultyHtml = course.difficulty
                    ? "<div class=\"llms-meta llms-difficulty\"><p>難易度: " + course.difficulty + "</p></div>"
                    : "";

                var lessonHtml = course.lesson_count
                    ? "<div class=\"llms-meta llms-lessons-count\"><p>レッスン数: " + course.lesson_count + "</p></div>"
                    : "";

                var dateHtml = course.enroll_date
                    ? "<div class=\"llms-meta llms-enroll-date\"><p>登録済み: " + course.enroll_date + "</p></div>"
                    : "";

                li.innerHTML =
                    "<div class=\"llms-loop-item-content\">"
                    + "<a class=\"llms-loop-link\" href=\"" + course.permalink + "\">"
                    + thumb
                    + "<h4 class=\"llms-loop-title\">" + course.title + "</h4>"
                    + "<footer class=\"llms-loop-item-footer\">"
                    + authorHtml
                    + lengthHtml
                    + difficultyHtml
                    + lessonHtml
                    + "<div class=\"llms-meta llms-enroll-status\"><p>ステータス: 登録済み</p></div>"
                    + dateHtml
                    + "</footer>"
                    + "</a>"
                    + "</div>";

                ul.appendChild( li );
            } );

            callback();
        } )
        .catch( function () { callback(); } );
    }

    // ---- ★ボタンを各カードに追加（DOM注入後に呼ぶ） ----
    function addButtons() {
        getItems().forEach( function ( item ) {
            if ( item.querySelector( ".sol-bm-btn" ) ) return;
            var courseId = getCourseId( item );
            if ( ! courseId ) return;

            var isBookmarked = solBookmark.bookmarks.indexOf( courseId ) !== -1;

            var btn = document.createElement( "button" );
            btn.className   = "sol-bm-btn" + ( isBookmarked ? " is-bookmarked" : "" );
            btn.textContent = isBookmarked ? "★" : "☆";
            btn.title       = "ブックマーク";
            btn.setAttribute( "data-course-id", courseId );

            btn.addEventListener( "click", function ( e ) {
                e.preventDefault();
                e.stopPropagation();
                toggleBookmark( courseId, btn, item );
            } );

            item.appendChild( btn );
            if ( isBookmarked ) item.classList.add( "sol-is-bookmarked" );
        } );
    }

    // ---- 並び替え ----
    function reorder() {
        ul.querySelectorAll( ".sol-separator" ).forEach( function ( el ) { el.remove(); } );
        getItems().forEach( function ( el ) { el.style.display = ""; } );

        var items = getItems();

        var bookmarked = solBookmark.bookmarks
            .map( function ( id ) {
                return items.find( function ( el ) { return getCourseId( el ) === id; } );
            } )
            .filter( Boolean );

        var normal = items.filter( function ( el ) {
            return solBookmark.bookmarks.indexOf( getCourseId( el ) ) === -1;
        } );

        if ( isDashboard ) {
            var DASHBOARD_MAX = 4;
            var bmShow        = bookmarked.slice( 0, DASHBOARD_MAX );
            var normalShow    = normal.slice( 0, DASHBOARD_MAX - bmShow.length );

            if ( bmShow.length > 0 ) {
                ul.appendChild( makeSeparator( "★ ブックマーク済み", "sol-bm-section-label" ) );
                bmShow.forEach( function ( el ) { ul.appendChild( el ); } );
                if ( normalShow.length > 0 ) {
                    ul.appendChild( makeSeparator( "その他のコース", "sol-bm-divider" ) );
                }
            }
            normalShow.forEach( function ( el ) { ul.appendChild( el ); } );

            var shown = bmShow.concat( normalShow );
            items.forEach( function ( el ) {
                if ( shown.indexOf( el ) === -1 ) el.style.display = "none";
            } );

        } else {
            if ( bookmarked.length > 0 ) {
                ul.appendChild( makeSeparator( "★ ブックマーク済み", "sol-bm-section-label" ) );
                bookmarked.forEach( function ( el ) { ul.appendChild( el ); } );
                if ( normal.length > 0 ) {
                    ul.appendChild( makeSeparator( "その他のコース", "sol-bm-divider" ) );
                }
            }
            normal.forEach( function ( el ) { ul.appendChild( el ); } );
        }
    }

    // ---- Ajax: ブックマーク切り替え ----
    function toggleBookmark( courseId, btn, item ) {
        btn.disabled = true;

        fetch( solBookmark.ajaxUrl, {
            method  : "POST",
            headers : { "Content-Type": "application/x-www-form-urlencoded" },
            body    : new URLSearchParams( {
                action    : "sol_toggle_bookmark",
                course_id : courseId,
                nonce     : solBookmark.nonce,
            } ).toString(),
        } )
        .then( function ( r ) { return r.json(); } )
        .then( function ( res ) {
            btn.disabled = false;
            if ( ! res.success ) return;

            solBookmark.bookmarks = res.data.bookmarks;
            var added = res.data.action === "added";

            btn.textContent = added ? "★" : "☆";
            btn.classList.toggle( "is-bookmarked", added );
            item.classList.toggle( "sol-is-bookmarked", added );

            showToast( added
                ? "★ ブックマークしました！先頭に表示されます"
                : "ブックマークを解除しました"
            );

            reorder();
        } )
        .catch( function () {
            btn.disabled = false;
            showToast( "通信エラーが発生しました。再度お試しください。" );
        } );
    }

    // ---- 初期化：不足コースを注入 → ボタン追加 → 並び替え ----
    injectMissingBookmarks( function () {
        addButtons();
        reorder();
    } );

} );
    ';

    wp_register_script( 'sol-bookmark', false, [], null, true );
    wp_enqueue_script( 'sol-bookmark' );
    wp_add_inline_script( 'sol-bookmark', $js_vars );
    wp_add_inline_script( 'sol-bookmark', $js );
}
add_action( 'wp_enqueue_scripts', 'sol_bookmark_assets' );


// -------------------------------------------------------
// 2. Ajax: ブックマーク済みコースのカードデータを返す
// -------------------------------------------------------
function sol_get_course_cards() {

    check_ajax_referer( 'sol_bookmark_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in', 401 );
    }

    $raw_ids = sanitize_text_field( $_POST['course_ids'] ?? '' );
    $ids     = array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) );

    if ( empty( $ids ) ) {
        wp_send_json_error( 'No IDs', 400 );
    }

    $courses = [];
    foreach ( $ids as $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'course' ) continue;

        $student = llms_get_student( get_current_user_id() );
        if ( ! $student || ! $student->is_enrolled( $id ) ) continue;

        // 難易度はタクソノミーから取得
        $difficulty_terms = get_the_terms( $id, 'course_difficulty' );
        $difficulty = ( $difficulty_terms && ! is_wp_error( $difficulty_terms ) )
            ? $difficulty_terms[0]->name : '';

        // 講師情報・レッスン数・予定時間
        $llms_course = llms_get_post( $id );
        $instructors = $llms_course ? $llms_course->get_instructors() : [];
        $instructor_name   = '';
        $instructor_avatar = '';
        if ( ! empty( $instructors ) ) {
            $instructor_name   = get_the_author_meta( 'display_name', $instructors[0]['id'] );
            $instructor_avatar = get_avatar_url( $instructors[0]['id'], [ 'size' => 28 ] );
        }
        $lesson_count = $llms_course ? count( $llms_course->get_lessons() ) : 0;
        $length       = $llms_course ? $llms_course->get( 'length' ) : '';

        // 登録日
        $enroll_date = '';
        $raw_date = $student->get_enrollment_date( $id, 'enrolled' );
        if ( $raw_date ) {
            $enroll_date = date_i18n( 'Y年n月j日', strtotime( $raw_date ) );
        }

        $courses[] = [
            'id'               => $id,
            'title'            => get_the_title( $id ),
            'permalink'        => get_permalink( $id ),
            'thumbnail'        => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
            'instructor_name'  => $instructor_name,
            'instructor_avatar'=> $instructor_avatar,
            'difficulty'       => $difficulty,
            'lesson_count'     => $lesson_count,
            'length'           => $length,
            'enroll_date'      => $enroll_date,
        ];
    }

    wp_send_json_success( [ 'courses' => $courses ] );
}
add_action( 'wp_ajax_sol_get_course_cards', 'sol_get_course_cards' );


// -------------------------------------------------------
// 3. Ajax: ブックマークの保存・解除
// -------------------------------------------------------
function sol_toggle_bookmark() {

    check_ajax_referer( 'sol_bookmark_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in', 401 );
    }

    $course_id = intval( $_POST['course_id'] ?? 0 );
    if ( $course_id <= 0 ) {
        wp_send_json_error( 'Invalid course ID', 400 );
    }

    $user_id   = get_current_user_id();
    $bookmarks = get_user_meta( $user_id, 'sol_bookmarked_courses', true );
    if ( ! is_array( $bookmarks ) ) {
        $bookmarks = [];
    }

    if ( in_array( $course_id, $bookmarks, true ) ) {
        $bookmarks = array_values( array_diff( $bookmarks, [ $course_id ] ) );
        $action    = 'removed';
    } else {
        array_unshift( $bookmarks, $course_id );
        $action = 'added';
    }

    update_user_meta( $user_id, 'sol_bookmarked_courses', $bookmarks );

    wp_send_json_success( [
        'action'    => $action,
        'bookmarks' => array_map( 'intval', $bookmarks ),
    ] );
}
add_action( 'wp_ajax_sol_toggle_bookmark', 'sol_toggle_bookmark' );



/**
 * ============================================================
 * 5 ダッシュボード お知らせティッカー
 * ============================================================
 * 
 * 【要確認】CPT UI で設定した「ニュース」投稿タイプのスラッグを
 *   SOL_NEWS_POST_TYPE に指定してください。
 *   例: CPT UIで "news" と設定した場合 → 'news'
 *       "sol_news" と設定した場合    → 'sol_news'
 */
 
// ▼▼▼ CPT UIで設定した投稿タイプスラッグに合わせてください ▼▼▼
define( 'SOL_NEWS_POST_TYPE', 'news' );
 
add_action( 'wp_enqueue_scripts', 'sol_news_ticker_assets' );
 
function sol_news_ticker_assets(): void {
    if ( ! function_exists( 'is_llms_account_page' ) ) return;
    if ( ! is_llms_account_page() )                    return;
    if ( ! is_user_logged_in() )                       return;
 
    $css = '
.llms-sd-tab {
    min-width: 0;
}
.sol-news-ticker-wrap {
    display: flex;
    align-items: stretch;
    box-sizing: border-box;
    background: #fff8f0;
    border: 1.5px solid #FFB74D;
    border-left: 5px solid #FF9800;
    border-radius: 8px;
    margin: 0 0 24px 0;
    overflow: hidden;
    min-height: 44px;
    max-width: 100%;
}
.sol-ticker-label {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 700;
    color: #E65100;
    white-space: nowrap;
    padding: 10px 14px;
    background: #FFF3E0;
    border-right: 1px solid #FFB74D;
}
.sol-ticker-viewport {
    flex: 1 1 0;
    width: 0;
    overflow: hidden;
    position: relative;
    min-width: 0;
}
.sol-ticker-viewport::before,
.sol-ticker-viewport::after {
    content: "";
    position: absolute;
    top: 0; bottom: 0;
    width: 24px;
    z-index: 1;
    pointer-events: none;
}
.sol-ticker-viewport::before {
    left: 0;
    background: linear-gradient(to right, #fff8f0, transparent);
}
.sol-ticker-viewport::after {
    right: 0;
    background: linear-gradient(to left, #fff8f0, transparent);
}
.sol-ticker-track {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    animation: sol-ticker-scroll linear infinite;
}
.sol-ticker-track:hover { animation-play-state: paused; }
.sol-ticker-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 48px 0 20px;
    font-size: 13px;
    line-height: 44px;
}
.sol-ticker-date {
    flex-shrink: 0;
    font-size: 11px;
    color: #999;
}
.sol-ticker-item a {
    color: #333;
    text-decoration: none;
}
.sol-ticker-item a:hover {
    color: #FF9800;
    text-decoration: underline;
}
.sol-ticker-more {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 600;
    color: #E65100;
    text-decoration: none;
    white-space: nowrap;
    padding: 0 14px;
    display: flex;
    align-items: center;
    background: #FFF3E0;
    border-left: 1px solid #FFB74D;
    transition: background 0.2s, color 0.2s;
}
.sol-ticker-more:hover { background: #FF9800; color: #fff; }
@keyframes sol-ticker-scroll {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
@media (max-width: 600px) {
    .sol-ticker-date  { display: none; }
    .sol-ticker-item  { padding: 0 28px 0 14px; font-size: 12px; }
    .sol-ticker-label { font-size: 12px; padding: 8px 10px; }
    .sol-ticker-more  { font-size: 11px; padding: 0 10px; }
}
@media (prefers-reduced-motion: reduce) {
    .sol-ticker-track { animation: none; overflow-x: auto; }
}
    ';
 
    wp_register_style( 'sol-news-ticker', false );
    wp_enqueue_style( 'sol-news-ticker' );
    wp_add_inline_style( 'sol-news-ticker', $css );
}
 
add_action( 'wp_footer', 'sol_news_ticker_footer_html', 20 );
 
function sol_news_ticker_footer_html(): void {
    if ( ! function_exists( 'is_llms_account_page' ) ) return;
    if ( ! is_llms_account_page() )                    return;
    if ( ! is_user_logged_in() )                       return;
 
    $news = get_posts( array(
        'post_type'      => SOL_NEWS_POST_TYPE,
        'posts_per_page' => 5,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );
 
    if ( empty( $news ) ) return;
 
    $archive_url = get_post_type_archive_link( SOL_NEWS_POST_TYPE ) ?: '';
    $duration    = max( 32, count( $news ) * 8 );
 
    $items_html = '';
    foreach ( $news as $post ) {
        $items_html .= sprintf(
            '<span class="sol-ticker-item">'
                . '<span class="sol-ticker-date">%s</span>'
                . '<a href="%s">%s</a>'
            . '</span>',
            esc_html( get_the_date( 'Y.m.d', $post ) ),
            esc_url( get_permalink( $post->ID ) ),
            esc_html( $post->post_title )
        );
    }
 
    $more_html = $archive_url
        ? '<a class="sol-ticker-more" href="' . esc_url( $archive_url ) . '">一覧 →</a>'
        : '';
    ?>
    <div id="sol-news-ticker" class="sol-news-ticker-wrap" style="display:none">
        <span class="sol-ticker-label"><span style="font-size:15px">📢</span>お知らせ</span>
        <div class="sol-ticker-viewport">
            <div class="sol-ticker-track" style="animation-duration:<?php echo esc_attr( $duration ); ?>s">
                <?php echo $items_html . $items_html; ?>
            </div>
        </div>
        <?php echo $more_html; ?>
    </div>
 
    <script>
    (function () {
        var ticker = document.getElementById('sol-news-ticker');
        if (!ticker) return;
 
        /*
         * ダッシュボードトップのみに表示する。
         *
         * LifterLMS のダッシュボードトップでは
         *   div.llms-sd-tab.dashboard  という要素が存在する。
         * サブページ（マイコース・成績・メンバーシップ等）では
         *   div.llms-sd-tab.my-courses / div.llms-sd-tab.grades 等になる。
         *
         * さらに親 div[data-current="dashboard"] でも確認する。
         */
 
        // ① ダッシュボードタブ（.dashboard クラス付き）を探す
        var dashboardTab = document.querySelector('.llms-sd-tab.dashboard');
 
        // ② 見つからなければ data-current="dashboard" の子を探す
        if (!dashboardTab) {
            var dashboardWrap = document.querySelector('[data-current="dashboard"]');
            if (dashboardWrap) {
                dashboardTab = dashboardWrap.querySelector('.llms-sd-tab');
            }
        }
 
        // ③ どちらも見つからない = ダッシュボードトップではない → 何もしない
        if (!dashboardTab) {
            ticker.remove();
            return;
        }
 
        // ダッシュボードタブの先頭に挿入
        dashboardTab.insertBefore(ticker, dashboardTab.firstChild);
        ticker.style.display = '';
    })();
    </script>
    <?php
}


/**
 * ============================================================
 * 6. AIチャットボット「明日架」
 * ============================================================
 */

// ▼ 明日架アイコン画像URL（本番環境用）
define( 'SOL_ASUKA_AVATAR_URL', 'https://switchonlab.online/wp-content/uploads/2026/05/asuka_default.png' );

// ▼ Dify APIキー（サーバー側のみ・絶対に公開しないこと）
define( 'SOL_DIFY_API_KEY', 'app-BTjW88bketL8BVSssiLibKjD' );
define( 'SOL_DIFY_API_URL', 'https://api.dify.ai/v1' );


// -------------------------------------------------------
// フロントエンド：CSS & JS の読み込み
// -------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'sol_chatbot_assets' );
function sol_chatbot_assets(): void {
   

    $avatar_url = SOL_ASUKA_AVATAR_URL;
    $ajax_url   = admin_url( 'admin-ajax.php' );
    $nonce      = wp_create_nonce( 'sol_chatbot_nonce' );

    $css = '
#sol-chat-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #FF6B00;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    z-index: 99990;
    padding: 0;
    overflow: hidden;
    transition: transform 0.2s;
}
#sol-chat-btn:hover { transform: scale(1.08); }
#sol-chat-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

#sol-chat-window {
    position: fixed;
    bottom: 100px;
    right: 28px;
    width: 370px;
    height: 520px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.22);
    z-index: 99991;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px);
    pointer-events: none;
    transition: opacity 0.25s, transform 0.25s;
}
#sol-chat-window.sol-open {
    opacity: 1;
    transform: translateY(0);
    pointer-events: all;
}
#sol-chat-header {
    background: #FF6B00;
    color: #fff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
#sol-chat-header img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.7);
}
#sol-chat-header .sol-chat-name { font-weight: 700; font-size: 15px; }
#sol-chat-header .sol-chat-sub  { font-size: 11px; opacity: 0.85; }
#sol-chat-close {
    margin-left: auto;
    background: none;
    border: none;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
    padding: 0 4px;
}
#sol-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 14px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #fafafa;
}
.sol-msg-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.sol-msg-row.sol-user { flex-direction: row-reverse; }
.sol-msg-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.sol-msg-bubble {
    max-width: 80%;
    padding: 10px 13px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.65;
    word-break: break-word;
}
.sol-msg-row.sol-bot  .sol-msg-bubble { background: #fff; border: 1px solid #eee; border-bottom-left-radius: 4px; }
.sol-msg-row.sol-user .sol-msg-bubble { background: #FF6B00; color: #fff; border-bottom-right-radius: 4px; }
.sol-msg-typing .sol-msg-bubble { color: #aaa; font-size: 13px; }
#sol-chat-input-area {
    display: flex;
    padding: 10px 12px;
    gap: 8px;
    border-top: 1px solid #eee;
    background: #fff;
    flex-shrink: 0;
}
#sol-chat-input {
    flex: 1;
    border: 1.5px solid #ddd;
    border-radius: 20px;
    padding: 8px 14px;
    font-size: 13.5px;
    outline: none;
    resize: none;
    line-height: 1.5;
    max-height: 80px;
    overflow-y: auto;
}
#sol-chat-input:focus { border-color: #FF6B00; }
#sol-chat-send {
    width: 38px;
    height: 38px;
    background: #FF6B00;
    border: none;
    border-radius: 50%;
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
    align-self: flex-end;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
#sol-chat-send:hover { background: #e05a00; }
#sol-chat-send:disabled { background: #ccc; cursor: default; }

@media (max-width: 480px) {
    #sol-chat-window {
        width: 100vw;
        height: 70vh;
        bottom: 0;
        right: 0;
        border-radius: 20px 20px 0 0;
    }
    #sol-chat-btn { bottom: 16px; right: 16px; }
}
';

    $js = '
(function () {
    var AJAX_URL = ' . json_encode( $ajax_url ) . ';
    var NONCE    = ' . json_encode( $nonce ) . ';
    var AVATAR   = ' . json_encode( $avatar_url ) . ';
    var convId   = sessionStorage.getItem("sol_chat_conv_id") || null;
    var sending  = false;

    var btn = document.createElement("button");
    btn.id    = "sol-chat-btn";
    btn.title = "明日架に質問する";
    btn.innerHTML = "<svg viewBox=\"0 0 24 24\" width=\"28\" height=\"28\" fill=\"#fff\"><path d=\"M12 2C6.48 2 2 5.94 2 10.8c0 2.77 1.5 5.23 3.86 6.86-.1.86-.42 2.4-1.16 3.99a.5.5 0 0 0 .64.68c1.9-.78 3.46-1.7 4.36-2.28.73.15 1.49.23 2.3.23 5.52 0 10-3.94 10-8.8S17.52 2 12 2z\"/></svg>";
    document.body.appendChild(btn);

    var win = document.createElement("div");
    win.id  = "sol-chat-window";
    win.innerHTML =
        "<div id=\"sol-chat-header\">" +
            (AVATAR ? "<img src=\"" + AVATAR + "\" alt=\"明日架\">" : "") +
            "<div><div class=\"sol-chat-name\">明日架</div><div class=\"sol-chat-sub\">スイッチオンラボ AIナビゲーター</div></div>" +
            "<button id=\"sol-chat-close\" title=\"閉じる\">✕</button>" +
        "</div>" +
        "<div id=\"sol-chat-messages\"></div>" +
        "<div id=\"sol-chat-input-area\">" +
            "<textarea id=\"sol-chat-input\" placeholder=\"質問を入力してください…\" rows=\"1\"></textarea>" +
            "<button id=\"sol-chat-send\"><svg viewBox=\"0 0 24 24\" width=\"18\" height=\"18\" fill=\"currentColor\"><path d=\"M2 21L23 12 2 3v7l15 2-15 2v7z\"/></svg></button>" +
        "</div>";
    document.body.appendChild(win);

    var messages = win.querySelector("#sol-chat-messages");
    var input    = win.querySelector("#sol-chat-input");
    var sendBtn  = win.querySelector("#sol-chat-send");

    btn.addEventListener("click", function () {
        var isOpen = win.classList.contains("sol-open");
        win.classList.toggle("sol-open", !isOpen);
        if (!isOpen && messages.children.length === 0) {
            var saved = sessionStorage.getItem("sol_chat_messages");
            if (saved) {
                messages.innerHTML = saved;
                messages.scrollTop = messages.scrollHeight;
            } else {
                addMessage("bot", "こんにちはなのです！<br>スイッチオンラボのナビゲーター、三貫地明日架なのです。<br>何でも聞いてほしいのです！");
            }
        }
        if (!isOpen) { setTimeout(function () { input.focus(); }, 300); }
    });

    win.querySelector("#sol-chat-close").addEventListener("click", function () {
        win.classList.remove("sol-open");
    });

    function addMessage(role, html) {
        var row = document.createElement("div");
        row.className = "sol-msg-row sol-" + role;
        if (role === "bot") {
            row.innerHTML =
                (AVATAR ? "<img class=\"sol-msg-avatar\" src=\"" + AVATAR + "\" alt=\"明日架\">" : "") +
                "<div class=\"sol-msg-bubble\">" + html + "</div>";
        } else {
            row.innerHTML = "<div class=\"sol-msg-bubble\">" + html + "</div>";
        }
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        sessionStorage.setItem("sol_chat_messages", messages.innerHTML);
        return row;
    }

    function addTyping() {
        var row = document.createElement("div");
        row.className = "sol-msg-row sol-bot sol-msg-typing";
        row.innerHTML =
            (AVATAR ? "<img class=\"sol-msg-avatar\" src=\"" + AVATAR + "\" alt=\"明日架\">" : "") +
            "<div class=\"sol-msg-bubble\">入力中…</div>";
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return row;
    }

    function escapeHtml(t) {
        return t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
    function formatText(t) {
        return escapeHtml(t)
        .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
        .replace(/\n/g, "<br>");
    }

    function sendMessage() {
        if (sending) return;
        var text = input.value.trim();
        if (!text) return;

        addMessage("user", escapeHtml(text));
        input.value = "";
        input.style.height = "";
        var typing = addTyping();
        sending = true;
        sendBtn.disabled = true;

        var params = new URLSearchParams({
            action          : "sol_chatbot_send",
            nonce           : NONCE,
            message         : text,
            conversation_id : convId || ""
        });

        fetch(AJAX_URL, {
            method  : "POST",
            headers : { "Content-Type": "application/x-www-form-urlencoded" },
            body    : params.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            typing.remove();
            if (res.success) {
                convId = res.data.conversation_id || convId;
                if (convId) sessionStorage.setItem("sol_chat_conv_id", convId);
                addMessage("bot", formatText(res.data.answer));
            } else {
                addMessage("bot", "ごめんなのです、うまく答えられませんでした。もう一度試してほしいのです。");
            }
        })
        .catch(function () {
            typing.remove();
            addMessage("bot", "通信エラーなのです。少し待ってから再度試してほしいのです。");
        })
        .finally(function () {
            sending = false;
            sendBtn.disabled = false;
        });
    }

    sendBtn.addEventListener("click", sendMessage);
    input.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    input.addEventListener("input", function () {
        this.style.height = "";
        this.style.height = Math.min(this.scrollHeight, 80) + "px";
    });
})();
';

    wp_register_style( 'sol-chatbot', false );
    wp_enqueue_style( 'sol-chatbot' );
    wp_add_inline_style( 'sol-chatbot', $css );

    wp_register_script( 'sol-chatbot', false, [], null, true );
    wp_enqueue_script( 'sol-chatbot' );
    wp_add_inline_script( 'sol-chatbot', $js );
}


// -------------------------------------------------------
// Ajax: Dify API へのプロキシ（APIキーはサーバー側のみ）
// -------------------------------------------------------
add_action( 'wp_ajax_sol_chatbot_send', 'sol_chatbot_send' );
function sol_chatbot_send(): void {
    check_ajax_referer( 'sol_chatbot_nonce', 'nonce' );

    
    $message         = sanitize_text_field( $_POST['message'] ?? '' );
    $conversation_id = sanitize_text_field( $_POST['conversation_id'] ?? '' );

    if ( empty( $message ) ) {
        wp_send_json_error( 'Empty message', 400 );
    }

    $body = array(
        'inputs'        => (object) [],
        'query'         => $message,
        'response_mode' => 'blocking',
        'user' => is_user_logged_in() ? 'wp_user_' . get_current_user_id() : 'guest_' . md5( $_SERVER['REMOTE_ADDR'] ),
    );
    if ( ! empty( $conversation_id ) ) {
        $body['conversation_id'] = $conversation_id;
    }

    $response = wp_remote_post( SOL_DIFY_API_URL . '/chat-messages', array(
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Bearer ' . SOL_DIFY_API_KEY,
            'Content-Type'  => 'application/json',
        ),
        'body' => wp_json_encode( $body ),
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'API request failed', 500 );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $data['answer'] ) ) {
        wp_send_json_error( 'No answer', 500 );
    }

    wp_send_json_success( array(
        'answer'          => $data['answer'],
        'conversation_id' => $data['conversation_id'] ?? '',
    ) );
}

// ============================================================
// 7) Scratchチュートリアル iframeショートコード（トークン認証付き）
// ============================================================

function sol_generate_scratch_iframe( $atts ) {
    $atts = shortcode_atts(
        array( 'project' => '' ),
        $atts,
        'switchonlab_scratch'
    );

    $project = sanitize_text_field( $atts['project'] );
    if ( empty( $project ) ) {
        return '<p>プロジェクトが指定されていません。</p>';
    }

    $secret  = defined( 'SOL_TOKEN_SECRET' ) ? SOL_TOKEN_SECRET : '';
    $expires = time() + 3600; // 1時間有効
    $token   = hash_hmac( 'sha256', $project . ':' . $expires, $secret );

    $base_url = 'https://switchonlab-scratch.vercel.app/';
    $url = add_query_arg(
        array(
            'project' => $project,
            'token'   => $token,
            'expires' => $expires,
        ),
        $base_url
    );

    return '<iframe src="' . esc_url( $url ) . '" width="100%" height="800px" frameborder="0" allowfullscreen></iframe>';
}
add_shortcode( 'switchonlab_scratch', 'sol_generate_scratch_iframe' );



// ============================================================
// 8) WordPress 7対応：ショートコード強制実行
// ============================================================
add_filter( 'the_content', 'do_shortcode' );