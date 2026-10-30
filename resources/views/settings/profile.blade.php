<article class="panel settings-card profile-settings">
    <h3>Profil saya</h3>
    <p>Perbarui foto dan identitas yang tampil di workspace.</p>
    <form id="profileForm" enctype="multipart/form-data">
        <div class="profile-photo-row">
            <div class="avatar profile-avatar" data-profile-avatar>
                @if(auth()->user()->avatar_url)<img src="{{ auth()->user()->avatar_url }}" alt="Foto profil">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif
            </div>
            <div class="profile-photo-actions">
                <label class="secondary-button photo-picker" for="profileAvatar">Pilih foto</label>
                <input id="profileAvatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
                <button class="text-button" id="removeAvatarButton" type="button">Hapus foto</button>
                <small>JPG, PNG, atau WebP. Maksimal 2 MB.</small>
                <span id="avatarFilename" class="profile-file-name"></span>
            </div>
        </div>
        <input name="remove_avatar" type="hidden" value="0">
        <label for="profileName">Nama lengkap<input id="profileName" name="name" value="{{ auth()->user()->name }}" autocomplete="name" maxlength="255" required></label>
        <label for="profileEmail">Email<input id="profileEmail" name="email" type="email" value="{{ auth()->user()->email }}" autocomplete="email" maxlength="255" required></label>
        <p class="profile-role-note">Peran: <b>{{ ucfirst(auth()->user()->role) }}</b> · Peran dikelola oleh admin.</p>
        <p id="profileFeedback" class="form-feedback" role="status" aria-live="polite"></p>
        <div class="profile-form-actions"><button class="primary-button" type="submit">Simpan perubahan</button><button class="secondary-button" type="reset">Batal</button></div>
    </form>
</article>
<article class="panel settings-card password-settings">
    <h3>Ganti kata sandi</h3>
    <p>Gunakan minimal delapan karakter dengan kombinasi huruf dan angka.</p>
    <form id="passwordForm">
        <label for="currentPassword">Kata sandi saat ini<input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required></label>
        <label for="newPassword">Kata sandi baru<input id="newPassword" name="password" type="password" autocomplete="new-password" minlength="8" required></label>
        <label for="confirmPassword">Konfirmasi kata sandi baru<input id="confirmPassword" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
        <label class="show-password-label"><input id="showProfilePasswords" type="checkbox"> Tampilkan kata sandi</label>
        <p id="passwordFeedback" class="form-feedback" role="status" aria-live="polite"></p>
        <button class="primary-button" type="submit">Perbarui kata sandi</button>
    </form>
</article>
