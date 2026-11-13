<?php
declare(strict_types=1);
?>
<!doctype html>
<!-- Struktura HTML e faqes; app.js e plotëson sipas llogarisë dhe rolit aktiv. -->
<html lang="sq">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f8fc">
    <title>Klasa ime</title>
    <link rel="stylesheet" href="/styles.css">
  </head>
  <body>
    <!-- Shiriti lart tregon emrin dhe, pasi përdoruesi hyn, butonin për dalje. -->
    <header class="topbar">
      <a class="brand" href="/" aria-label="Klasa ime - faqja kryesore">
        <span class="brand-mark" aria-hidden="true">K</span>
        <span>Klasa ime</span>
      </a>
      <span class="topbar-note">Hapësira jote e të nxënit</span>
      <div class="user-actions" id="user-actions" hidden>
        <span class="user-label" id="user-label"></span>
        <button class="text-button" id="logout-button" type="button">Dil</button>
      </div>
    </header>

    <!-- Mësuesit dhe nxënësit përdorin të njëjtën faqe hyrjeje/regjistrimi. -->
    <section class="auth-panel" id="auth-panel" hidden>
      <div class="auth-welcome">
        <p class="eyebrow">MËSIMI, MË I ORGANIZUAR</p>
        <h1>Klasa jote. Gjithçka në një vend.</h1>
        <p>Mësuesit krijojnë klasa e detyra. Nxënësit bashkohen me kod dhe dorëzojnë punën e tyre.</p>
        <div class="auth-benefits">
          <span>✓ Klasa me kod ftese</span>
          <span>✓ Detyra dhe afate</span>
          <span>✓ Dorëzime për çdo nxënës</span>
        </div>
      </div>

      <!-- Formulari i hyrjes përdor email-in dhe fjalëkalimin e llogarisë. -->
      <div class="form-card auth-card" id="login-card">
        <p class="eyebrow">MIRË SE U KTHYVE</p>
        <h2>Hyr në llogarinë tënde</h2>
        <form id="login-form">
          <label for="login-email">Email-i</label>
          <input id="login-email" name="email" type="email" maxlength="254" autocomplete="email" required placeholder="emri@email.com">
          <label for="login-password">Fjalëkalimi</label>
          <input id="login-password" name="password" type="password" autocomplete="current-password" required placeholder="Fjalëkalimi">
          <button class="primary-button auth-submit" type="submit">Hyr</button>
        </form>
        <p class="auth-switch">Nuk ke llogari? <button class="inline-button" id="show-register" type="button">Regjistrohu</button></p>
      </div>

      <!-- Gjatë regjistrimit zgjidhet roli që kontrollon çfarë mund të bëjë përdoruesi. -->
      <div class="form-card auth-card" id="register-card" hidden>
        <p class="eyebrow">FILLIM I RI</p>
        <h2>Krijo llogarinë</h2>
        <form id="register-form">
          <label for="register-name">Emri yt</label>
          <input id="register-name" name="name" maxlength="100" autocomplete="name" required placeholder="Emri dhe mbiemri">
          <label for="register-email">Email-i</label>
          <input id="register-email" name="email" type="email" maxlength="254" autocomplete="email" required placeholder="emri@email.com">
          <label for="register-password">Fjalëkalimi <span class="optional">(të paktën 8 karaktere)</span></label>
          <input id="register-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required placeholder="Krijo fjalëkalim">
          <label for="register-role">Regjistrohu si</label>
          <select id="register-role" name="role" required>
            <option value="teacher">Mësues</option>
            <option value="student">Nxënës</option>
          </select>
          <button class="primary-button auth-submit" type="submit">Krijo llogarinë</button>
        </form>
        <p class="auth-switch">Ke llogari? <button class="inline-button" id="show-login" type="button">Hyr këtu</button></p>
      </div>
    </section>

    <!-- Hapësira e klasave shfaqet pasi përdoruesi kyçet me sukses. -->
    <div class="layout" id="workspace" hidden>
      <aside class="sidebar">
        <div class="sidebar-heading">
          <div>
            <p class="eyebrow">HAPËSIRA IME</p>
            <h1>Klasat</h1>
          </div>
          <button class="icon-button" id="new-course-button" type="button" aria-label="Krijo klasë" title="Krijo klasë">+</button>
        </div>
        <button class="new-course-link" id="new-course-link" type="button">
          <span aria-hidden="true">＋</span> Krijo klasë të re
        </button>
        <!-- Nxënësi shkruan këtu kodin e ftesës që i ka dhënë mësuesi. -->
        <form class="join-class-form" id="join-class-form" hidden>
          <label for="join-class-code">Bashkohu me kodin e klasës</label>
          <div class="join-class-controls">
            <input id="join-class-code" name="class_code" maxlength="8" required placeholder="p.sh. ABCD2345" aria-label="Kodi i klasës">
            <button class="icon-button" type="submit" aria-label="Bashkohu me klasën" title="Bashkohu me klasën">↗</button>
          </div>
        </form>
        <nav id="course-list" class="course-list" aria-label="Klasat e mia"></nav>
        <div class="sidebar-footer">Mëso. Bashkëpuno. Përparoni.</div>
      </aside>

      <!-- Përmbajtja kryesore paraqet mesazhin hyrës ose klasën e zgjedhur. -->
      <main class="main-content">
        <section class="welcome-panel" id="welcome-panel">
          <div class="welcome-copy">
            <p class="eyebrow" id="welcome-eyebrow">MIRË SE ERDHE</p>
            <h2 id="welcome-heading">Një vend për klasën tënde.</h2>
            <p id="welcome-description">Krijo klasën e parë dhe mbaji mësimet e detyrat të organizuara.</p>
            <button class="primary-button" id="welcome-create-button" type="button">Krijo klasë</button>
            <button class="primary-button" id="welcome-join-button" type="button" hidden>Bashkohu me klasë</button>
          </div>
          <div class="welcome-art" aria-hidden="true">
            <span class="art-sun"></span>
            <span class="art-book">Aa</span>
            <span class="art-leaf"></span>
          </div>
        </section>

        <section class="class-view" id="class-view" hidden>
          <div class="class-banner">
            <div class="class-banner-copy">
              <p class="eyebrow">KLASA JOTE</p>
              <h2 id="class-name"></h2>
              <p id="class-teacher"></p>
            </div>
            <span class="banner-book" aria-hidden="true">Aa</span>
          </div>
          <div class="class-code-box" id="class-code-box" hidden>
            <span>Ndajeni këtë kod me nxënësit për t'i ftuar në klasë.</span>
            <strong id="class-code"></strong>
          </div>
          <div class="class-description" id="class-description"></div>
          <div class="section-heading">
            <div>
              <p class="eyebrow">MËSIMI VAZHDON</p>
              <h3>Detyrat</h3>
            </div>
            <button class="primary-button" id="new-assignment-button" type="button">
              <span aria-hidden="true">＋</span> Shto detyrë
            </button>
          </div>
          <form class="form-card" id="assignment-form" hidden>
            <h3>Detyrë e re</h3>
            <label for="assignment-title">Titulli</label>
            <input id="assignment-title" name="title" maxlength="150" required placeholder="p.sh. Ushtrime matematike">
            <label for="assignment-details">Udhëzime <span class="optional">(opsionale)</span></label>
            <textarea id="assignment-details" name="details" maxlength="2000" rows="3" placeholder="Shkruaj çfarë duhet të bëhet..."></textarea>
            <label for="assignment-due-date">Afati <span class="optional">(opsional)</span></label>
            <input id="assignment-due-date" name="due_date" type="date">
            <div class="form-actions">
              <button class="text-button" type="button" data-close-form="assignment-form">Anulo</button>
              <button class="primary-button" type="submit">Ruaj detyrën</button>
            </div>
          </form>
          <!-- Kartat e detyrave mbushen nga API sipas rolit të përdoruesit. -->
          <div class="assignment-list" id="assignment-list"></div>
        </section>
      </main>
    </div>

    <!-- Dialogu përdoret vetëm nga mësuesi për hapjen e një klase të re. -->
    <dialog class="course-dialog" id="course-dialog">
      <form class="form-card dialog-card" id="course-form">
        <div class="dialog-heading">
          <div>
            <p class="eyebrow">FILLIM I RI</p>
            <h2>Krijo një klasë</h2>
          </div>
          <button class="icon-button close-button" id="close-course-dialog" type="button" aria-label="Mbyll">×</button>
        </div>
        <label for="course-name">Emri i klasës</label>
        <input id="course-name" name="name" maxlength="100" required placeholder="p.sh. Biologji 9A">
        <label for="course-description">Përshkrimi <span class="optional">(opsional)</span></label>
        <textarea id="course-description" name="description" maxlength="500" rows="3" placeholder="Disa fjalë për klasën..."></textarea>
        <div class="form-actions">
          <button class="text-button" id="cancel-course-dialog" type="button">Anulo</button>
          <button class="primary-button" type="submit">Krijo klasën</button>
        </div>
      </form>
    </dialog>

    <!-- Mesazhet u tregojnë përdoruesve nëse veprimi u krye ose dështoi. -->
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="/app.js" defer></script>
  </body>
</html>
