<form id="formSpkKriteria-{{ $lowongan->lowongan_id }}" action="{{ route('industri.lowongan.spk.calculate', $lowongan->lowongan_id) }}" method="POST">
    @csrf
    <p class="text-muted small">
        Tentukan bobot (tingkat kepentingan relatif) untuk setiap kriteria. Total akumulasi bobot idealnya adalah 100%.
    </p>
    <p class="text-muted small">
        Sistem memberi kebebasan dalam menentukan bobot, namun disarankan untuk <strong>skill yang lebih penting diberikan bobot lebih tinggi.</strong>
    </p>

    <div id="kriteriaCountError-{{ $lowongan->lowongan_id }}" class="alert alert-warning py-2 mb-2" style="display: none; font-size: 0.875rem;"></div>
    <div id="totalBobotError-{{ $lowongan->lowongan_id }}" class="alert py-2" style="display: none; font-size: 0.875rem;"></div>

    <div class="d-flex justify-content-end align-items-center mb-3 text-end">
        <span class="fw-bold me-4">Kriteria Terpilih: <span id="currentKriteriaCount-{{ $lowongan->lowongan_id }}">0</span></span>
        <span class="fw-bold">Total Bobot: <span id="currentTotalBobot-{{ $lowongan->lowongan_id }}">0</span>%</span>
    </div>

    <h6 class="text-dark-blue fw-bold border-bottom pb-2 mb-3">Kriteria Keahlian (Skill)</h6>
    @if($lowongan->lowonganSkill->isEmpty())
        <div class="alert alert-secondary">Lowongan ini tidak memiliki kriteria skill spesifik.</div>
    @else
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40%;">Keahlian yang Dibutuhkan</th>
                        <th class="text-center" style="width: 25%;">Target Level Kompetensi</th>
                        <th class="text-center" style="width: 35%;">Bobot Skill (%) <span class="text-danger">*</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowongan->lowonganSkill as $index => $reqSkill)
                        <tr>
                            <td>{{ optional($reqSkill->skill)->skill_nama ?? 'Skill tidak ada' }}</td>
                            <td class="text-center"><span class="badge bg-info">{{ $reqSkill->level_kompetensi }}</span></td>
                            <td>
                                <input type="number" class="form-control form-control-sm spk-bobot-input"
                                       name="bobot_skill[{{ $reqSkill->skill_id }}]"
                                       value="0"
                                       min="0" max="100" step="1" required
                                       aria-label="Bobot untuk {{ optional($reqSkill->skill)->skill_nama }}">
                                @error('bobot_skill.' . $reqSkill->skill_id) <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h6 class="text-dark-blue fw-bold border-bottom pb-2 mb-3 mt-4">Kriteria Tambahan</h6>
    {{-- IPK --}}
    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input spk-bobot-toggle" id="gunakan_ipk-{{ $lowongan->lowongan_id }}" name="gunakan_ipk" value="1"
            {{ old('gunakan_ipk', true) ? 'checked' : '' }}
            onchange="toggleKriteriaTambahan(this, 'ipk', '{{ $lowongan->lowongan_id }}')">
        <label class="form-check-label" for="gunakan_ipk-{{ $lowongan->lowongan_id }}">Sertakan Nilai Akademik (IPK)</label>
    </div>
    <div class="mb-3 ps-4" id="bobot_ipk_div-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_ipk', true) ? '' : 'display: none;' }}">
        <label for="bobot_ipk-{{ $lowongan->lowongan_id }}" class="form-label form-label-sm">Bobot IPK (%): <span class="text-danger" id="ipk_required_star-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_ipk', true) ? 'display:inline;' : 'display:none;' }}">*</span></label>
        <input type="number" class="form-control form-control-sm spk-bobot-input @error('bobot_ipk') is-invalid @enderror" id="bobot_ipk-{{ $lowongan->lowongan_id }}" name="bobot_ipk"
               value="{{ old('bobot_ipk', 20) }}" min="0" max="100" step="1"
               aria-label="Bobot untuk IPK" {{ old('gunakan_ipk', true) ? 'required' : '' }}>
        @error('bobot_ipk') <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Organisasi --}}
    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input spk-bobot-toggle" id="gunakan_organisasi-{{ $lowongan->lowongan_id }}" name="gunakan_organisasi" value="1"
            {{ old('gunakan_organisasi') ? 'checked' : '' }}
            onchange="toggleKriteriaTambahan(this, 'organisasi', '{{ $lowongan->lowongan_id }}')">
        <label class="form-check-label" for="gunakan_organisasi-{{ $lowongan->lowongan_id }}">Sertakan Aktivitas Organisasi</label>
    </div>
    <div class="mb-3 ps-4" id="bobot_organisasi_div-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_organisasi') ? '' : 'display: none;' }}">
        <label for="bobot_organisasi-{{ $lowongan->lowongan_id }}" class="form-label form-label-sm">Bobot Organisasi (%): <span class="text-danger" id="organisasi_required_star-{{ $lowongan->lowongan_id }}" style="display:none;">*</span></label>
        <input type="number" class="form-control form-control-sm spk-bobot-input @error('bobot_organisasi') is-invalid @enderror" id="bobot_organisasi-{{ $lowongan->lowongan_id }}" name="bobot_organisasi"
               value="{{ old('bobot_organisasi', 10) }}" min="0" max="100" step="1" aria-label="Bobot untuk Organisasi">
        @error('bobot_organisasi') <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Lomba --}}
    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input spk-bobot-toggle" id="gunakan_lomba-{{ $lowongan->lowongan_id }}" name="gunakan_lomba" value="1"
            {{ old('gunakan_lomba') ? 'checked' : '' }}
            onchange="toggleKriteriaTambahan(this, 'lomba', '{{ $lowongan->lowongan_id }}')">
        <label class="form-check-label" for="gunakan_lomba-{{ $lowongan->lowongan_id }}">Sertakan Aktivitas Lomba/Kompetisi</label>
    </div>
    <div class="mb-3 ps-4" id="bobot_lomba_div-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_lomba') ? '' : 'display: none;' }}">
        <label for="bobot_lomba-{{ $lowongan->lowongan_id }}" class="form-label form-label-sm">Bobot Lomba (%): <span class="text-danger" id="lomba_required_star-{{ $lowongan->lowongan_id }}" style="display:none;">*</span></label>
        <input type="number" class="form-control form-control-sm spk-bobot-input @error('bobot_lomba') is-invalid @enderror" id="bobot_lomba-{{ $lowongan->lowongan_id }}" name="bobot_lomba"
               value="{{ old('bobot_lomba', 10) }}" min="0" max="100" step="1" aria-label="Bobot untuk Lomba">
        @error('bobot_lomba') <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Skor AIS --}}
    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input spk-bobot-toggle" id="gunakan_skor_ais-{{ $lowongan->lowongan_id }}" name="gunakan_skor_ais" value="1"
            {{ old('gunakan_skor_ais') ? 'checked' : '' }}
            onchange="toggleKriteriaTambahan(this, 'skor_ais', '{{ $lowongan->lowongan_id }}')">
        <label class="form-check-label" for="gunakan_skor_ais-{{ $lowongan->lowongan_id }}">Sertakan Skor AIS (Semakin Rendah Semakin Baik)</label>
    </div>
    <div class="mb-3 ps-4" id="bobot_skor_ais_div-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_skor_ais') ? '' : 'display: none;' }}">
        <label for="bobot_skor_ais-{{ $lowongan->lowongan_id }}" class="form-label form-label-sm">Bobot Skor AIS (%): <span class="text-danger" id="skor_ais_required_star-{{ $lowongan->lowongan_id }}" style="display:none;">*</span></label>
        <input type="number" class="form-control form-control-sm spk-bobot-input @error('bobot_skor_ais') is-invalid @enderror" id="bobot_skor_ais-{{ $lowongan->lowongan_id }}" name="bobot_skor_ais"
               value="{{ old('bobot_skor_ais', 15) }}" min="0" max="100" step="1" aria-label="Bobot untuk Skor AIS">
        @error('bobot_skor_ais') <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Kasus Pelanggaran --}}
    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input spk-bobot-toggle" id="gunakan_kasus-{{ $lowongan->lowongan_id }}" name="gunakan_kasus" value="1"
            {{ old('gunakan_kasus', true) ? 'checked' : '' }}
            onchange="toggleKriteriaTambahan(this, 'kasus', '{{ $lowongan->lowongan_id }}')">
        <label class="form-check-label" for="gunakan_kasus-{{ $lowongan->lowongan_id }}">Sertakan Status Kasus Pelanggaran (Tidak Ada Kasus Lebih Baik)</label>
    </div>
    <div class="mb-3 ps-4" id="bobot_kasus_div-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_kasus', true) ? '' : 'display: none;' }}">
        <label for="bobot_kasus-{{ $lowongan->lowongan_id }}" class="form-label form-label-sm">Bobot Status Kasus (%): <span class="text-danger" id="kasus_required_star-{{ $lowongan->lowongan_id }}" style="{{ old('gunakan_kasus', true) ? 'display:inline;' : 'display:none;' }}">*</span></label>
        <input type="number" class="form-control form-control-sm spk-bobot-input @error('bobot_kasus') is-invalid @enderror" id="bobot_kasus-{{ $lowongan->lowongan_id }}" name="bobot_kasus"
               value="{{ old('bobot_kasus', 15) }}" min="0" max="100" step="1" aria-label="Bobot untuk Status Kasus" {{ old('gunakan_kasus', true) ? 'required' : '' }}>
        @error('bobot_kasus') <div class="invalid-feedback text-xs d-block">{{ $message }}</div> @enderror
    </div>

    {{-- Script untuk toggle dan kalkulasi bobot --}}
    <script>
        (function() {
            const lowonganId = '{{ $lowongan->lowongan_id }}';
            const formSpk = document.getElementById('formSpkKriteria-' + lowonganId);
            if (!formSpk) return;

            const MIN_KRITERIA = 5; // Tentukan jumlah minimal kriteria di sini

            // Elemen UI
            const currentTotalBobotSpan = document.getElementById('currentTotalBobot-' + lowonganId);
            const totalBobotErrorDiv = document.getElementById('totalBobotError-' + lowonganId);
            const currentKriteriaCountSpan = document.getElementById('currentKriteriaCount-' + lowonganId);
            const kriteriaCountErrorDiv = document.getElementById('kriteriaCountError-' + lowonganId);
            const submitButton = formSpk.querySelector('button[type="submit"]');

            function updateFormState() {
                if (!currentTotalBobotSpan || !totalBobotErrorDiv || !currentKriteriaCountSpan || !kriteriaCountErrorDiv) return;

                let totalBobot = 0;
                let kriteriaCount = formSpk.querySelectorAll('input[name^="bobot_skill"]').length;

                const bobotInputs = formSpk.querySelectorAll('.spk-bobot-input');

                // Hitung total bobot
                bobotInputs.forEach(input => {
                    if (input.offsetParent !== null) { // Hanya hitung input yang terlihat
                        totalBobot += parseFloat(input.value) || 0;
                    }
                });

                // Hitung jumlah kriteria tambahan yang aktif
                formSpk.querySelectorAll('.spk-bobot-toggle:checked').forEach(() => {
                    kriteriaCount++;
                });

                // Update tampilan jumlah
                currentTotalBobotSpan.textContent = totalBobot;
                currentKriteriaCountSpan.textContent = kriteriaCount;

                // Validasi dan tampilkan pesan untuk jumlah kriteria
                const isKriteriaValid = kriteriaCount >= MIN_KRITERIA;
                if (!isKriteriaValid) {
                    kriteriaCountErrorDiv.textContent = `Peringatan: Anda harus memilih minimal ${MIN_KRITERIA} kriteria untuk perhitungan (saat ini ${kriteriaCount} terpilih).`;
                    kriteriaCountErrorDiv.className = 'alert alert-danger py-2 mb-2 text-white';
                    kriteriaCountErrorDiv.style.display = 'block';
                } else {
                    kriteriaCountErrorDiv.style.display = 'none';
                }

                // Validasi dan tampilkan pesan untuk total bobot
                let isBobotValid = false;
                if (totalBobot > 100) {
                    totalBobotErrorDiv.textContent = 'Peringatan: Total bobot melebihi 100% (' + totalBobot + '%). Sistem akan menormalisasi bobot ini, namun disarankan total bobot adalah 100%.';
                    totalBobotErrorDiv.className = 'alert alert-warning py-2 text-white';
                    totalBobotErrorDiv.style.display = 'block';
                } else if (totalBobot < 100 && totalBobot > 0) {
                    totalBobotErrorDiv.textContent = 'Info: Total bobot saat ini adalah ' + totalBobot + '%. Idealnya 100%. Sistem akan menormalisasi bobot ini.';
                    totalBobotErrorDiv.className = 'alert alert-info py-2 text-white';
                    totalBobotErrorDiv.style.display = 'block';
                } else if (totalBobot === 100) {
                    totalBobotErrorDiv.textContent = 'Total bobot sudah 100%. Sempurna!';
                    totalBobotErrorDiv.className = 'alert alert-success py-2 text-white';
                    totalBobotErrorDiv.style.display = 'block';
                    isBobotValid = true; // Anggap valid jika pas 100
                } else {
                    totalBobotErrorDiv.style.display = 'none';
                }

                // Aktifkan atau nonaktifkan tombol submit
                if(submitButton) {
                    submitButton.disabled = !isKriteriaValid;
                }
            }

            window['toggleKriteriaTambahan'] = function(checkboxElement, kriteriaNama, lwId) {
                if (lwId !== lowonganId) return;

                const bobotDiv = document.getElementById('bobot_' + kriteriaNama + '_div-' + lwId);
                const bobotInputEl = document.getElementById('bobot_' + kriteriaNama + '-' + lwId);
                const requiredStarEl = document.getElementById(kriteriaNama + '_required_star-' + lwId);

                if (bobotDiv && bobotInputEl && requiredStarEl) {
                    if (checkboxElement.checked) {
                        bobotDiv.style.display = 'block';
                        bobotInputEl.required = true;
                        requiredStarEl.style.display = 'inline';
                    } else {
                        bobotDiv.style.display = 'none';
                        bobotInputEl.required = false;
                        requiredStarEl.style.display = 'none';
                    }
                    updateFormState(); // Panggil update setiap kali checkbox di-toggle
                }
            };

            // Tambahkan event listener ke semua input dan checkbox
            formSpk.querySelectorAll('.spk-bobot-input, .spk-bobot-toggle').forEach(input => {
                input.addEventListener('input', updateFormState);
                input.addEventListener('change', updateFormState);
            });

            // Panggil fungsi saat halaman pertama kali dimuat
            updateFormState();
        })();
    </script>

    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-success">
            <i class="fas fa-calculator me-1"></i> Hitung Rekomendasi
        </button>
    </div>

    <div id="spkResultArea-{{ $lowongan->lowongan_id }}" class="mt-4"></div>
    <hr class="my-4">
    <div class="alert alert-outline-white small" role="alert" style="background-color: #f8f9fa; border-color: #e9ecef;">
        Rekomendasi kriteria ini akan membantu dalam menentukan kandidat terbaik berdasarkan bobot yang telah ditentukan. Pastikan untuk meninjau hasil rekomendasi sebelum membuat keputusan akhir.
    </div>
</form>
