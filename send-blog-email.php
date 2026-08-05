<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

function sendNewBlogEmail($post_id, $cont) {

    // ─── SMTP CONFIG ────────────────────────────────────────────────────────────
    $smtp_username = "newzely.com@gmail.com";
    $smtp_password = "sqvh wnan bsfg wnhh";
    $from_email    = "newzely.com@gmail.com";
    $from_name     = "Newzely";

    // ─── FETCH POST ─────────────────────────────────────────────────────────────
    $post = mysqli_fetch_assoc(mysqli_query($cont, "SELECT * FROM blog_posts WHERE id = " . (int)$post_id));
    if (!$post) return 0;

    // ─── BUILD CANONICAL URL (fixes old post.php?slug= broken link) ─────────────
    $post_url       = "https://newzely.com/news/" . rawurlencode($post['slug']) . ".php";
    $unsubscribe_base = "https://newzely.com/unsubscribe.php?token=";

    // ─── EXCERPT ────────────────────────────────────────────────────────────────
    $excerpt = htmlspecialchars(
        mb_substr(strip_tags(html_entity_decode($post['content'], ENT_QUOTES, 'UTF-8')), 0, 220),
        ENT_QUOTES, 'UTF-8'
    );
    if (mb_strlen(strip_tags($post['content'])) > 220) $excerpt .= '...';

    // ─── OG IMAGE ───────────────────────────────────────────────────────────────
    $og_img = '';
    if (!empty($post['og_image']))       $og_img = $post['og_image'];
    elseif (!empty($post['image']))      $og_img = $post['image'];
    elseif (!empty($post['video_thumbnail'])) $og_img = $post['video_thumbnail'];
    if ($og_img && !filter_var($og_img, FILTER_VALIDATE_URL)) {
        $og_img = 'https://newzely.com/' . ltrim($og_img, '/');
    }

    $title_safe    = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
    $author_safe   = htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8');
    $category_safe = htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8');
    $read_time     = (int)$post['read_time'];
    $subject       = "📰 " . $post['title'] . " — Newzely";

    // ─── SUBSCRIBERS ────────────────────────────────────────────────────────────
    $subscribers_result = mysqli_query($cont, "SELECT * FROM email_subscribers WHERE is_active = 1");
    if (!$subscribers_result || mysqli_num_rows($subscribers_result) === 0) return 0;

    $sent_count = 0;

    while ($subscriber = mysqli_fetch_assoc($subscribers_result)) {
        $email = $subscriber['email'];
        $token = $subscriber['unsubscribe_token'];

        // Skip if already sent to this subscriber for this post
        $already = mysqli_fetch_assoc(mysqli_query($cont,
            "SELECT id FROM email_logs WHERE subscriber_id = {$subscriber['id']} AND post_id = $post_id"
        ));
        if ($already) continue;

        $unsubscribe_url = $unsubscribe_base . urlencode($token);

        // ─── EMAIL HTML ─────────────────────────────────────────────────────────
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $title_safe . '</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

    <!-- HEADER -->
    <tr>
      <td style="background:linear-gradient(135deg,#1e293b 0%,#3b82f6 100%);padding:32px 40px;text-align:center;">
        <a href="https://newzely.com" style="text-decoration:none;">
          <div style="font-size:30px;font-weight:900;color:#ffffff;letter-spacing:-1px;font-family:Georgia,serif;">
            Newzely
          </div>
          <div style="font-size:13px;color:rgba(255,255,255,0.75);margin-top:4px;letter-spacing:2px;text-transform:uppercase;">Breaking News &amp; Stories</div>
        </a>
      </td>
    </tr>

    <!-- CATEGORY BADGE -->
    <tr>
      <td style="padding:24px 40px 0 40px;">
        <span style="display:inline-block;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:5px 14px;border-radius:999px;border:1px solid #bfdbfe;">
          ' . $category_safe . '
        </span>
      </td>
    </tr>

    <!-- TITLE -->
    <tr>
      <td style="padding:16px 40px 0 40px;">
        <h1 style="margin:0;font-size:26px;font-weight:800;color:#0f172a;line-height:1.3;font-family:Georgia,serif;">
          ' . $title_safe . '
        </h1>
      </td>
    </tr>

    <!-- META -->
    <tr>
      <td style="padding:12px 40px 0 40px;">
        <p style="margin:0;font-size:13px;color:#64748b;">
          ✍️ By <strong style="color:#475569;">' . $author_safe . '</strong>
          &nbsp;&nbsp;•&nbsp;&nbsp;
          📖 <strong style="color:#475569;">' . $read_time . ' min read</strong>
        </p>
      </td>
    </tr>

    ' . ($og_img ? '
    <!-- HERO IMAGE -->
    <tr>
      <td style="padding:20px 40px 0 40px;">
        <a href="' . $post_url . '" style="display:block;border-radius:10px;overflow:hidden;">
          <img src="' . htmlspecialchars($og_img, ENT_QUOTES, 'UTF-8') . '"
               alt="' . $title_safe . '"
               width="520"
               style="width:100%;max-width:520px;height:auto;display:block;border-radius:10px;"
               onerror="this.style.display=\'none\'">
        </a>
      </td>
    </tr>' : '') . '

    <!-- EXCERPT -->
    <tr>
      <td style="padding:20px 40px 0 40px;">
        <p style="margin:0;font-size:16px;color:#475569;line-height:1.7;">
          ' . $excerpt . '
        </p>
      </td>
    </tr>

    <!-- CTA BUTTON -->
    <tr>
      <td style="padding:28px 40px 0 40px;text-align:center;">
        <a href="' . $post_url . '"
           style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:10px;letter-spacing:0.3px;">
          Read Full Story &rarr;
        </a>
      </td>
    </tr>

    <!-- DIVIDER -->
    <tr>
      <td style="padding:32px 40px 0 40px;">
        <div style="height:1px;background:#e2e8f0;"></div>
      </td>
    </tr>

    <!-- MORE FROM NEWZELY blurb -->
    <tr>
      <td style="padding:20px 40px 0 40px;text-align:center;">
        <p style="margin:0;font-size:14px;color:#94a3b8;">
          Want more stories?
          <a href="https://newzely.com" style="color:#2563eb;text-decoration:none;font-weight:600;">Visit Newzely.com</a>
        </p>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="padding:20px 40px 32px 40px;text-align:center;background:#f8fafc;margin-top:20px;">
        <p style="margin:0 0 8px 0;font-size:12px;color:#94a3b8;">
          You are receiving this because you subscribed to Newzely updates.
        </p>
        <p style="margin:0;font-size:12px;">
          <a href="' . $unsubscribe_url . '" style="color:#94a3b8;text-decoration:underline;">Unsubscribe</a>
          &nbsp;&nbsp;•&nbsp;&nbsp;
          <a href="https://newzely.com/privacy-policy.php" style="color:#94a3b8;text-decoration:underline;">Privacy Policy</a>
        </p>
        <p style="margin:12px 0 0 0;font-size:11px;color:#cbd5e1;">&copy; ' . date('Y') . ' Newzely. All rights reserved.</p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>

</body>
</html>';

        // ─── SEND VIA PHPMAILER ──────────────────────────────────────────────────
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags("New story on Newzely: " . $post['title'] . "\n\n" . strip_tags($excerpt) . "\n\nRead it here: " . $post_url);

            if ($mail->send()) {
                mysqli_query($cont, "INSERT INTO email_logs (subscriber_id, post_id) VALUES ({$subscriber['id']}, $post_id)");
                $sent_count++;
            }
            $mail->clearAddresses();

        } catch (Exception $e) {
            error_log("Newzely email to {$email} failed: {$mail->ErrorInfo}");
        }
    }

    return $sent_count;
}

// ─── DIRECT URL TRIGGER — only runs when accessed directly (not via include) ──
if (basename($_SERVER['PHP_SELF']) === 'send-blog-email.php' && isset($_GET['post_id'])) {
    include_once("connection.php");
    $result = sendNewBlogEmail((int)$_GET['post_id'], $cont);
    header("Location: blog_admin.php?email_sent=" . $result);
    exit();
}