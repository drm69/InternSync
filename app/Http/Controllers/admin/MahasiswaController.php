<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\DosenModel;
use App\Models\LevelModel;
use App\Models\MahasiswaModel;
use App\Models\ProdiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class MahasiswaController extends Controller
{
    public function index()
    {
        $mahasiswa  = MahasiswaModel::with('prodi')->get();
        $activeMenu = 'mahasiswa';
        return view('admin_page.mahasiswa.index', compact('mahasiswa', 'activeMenu'));
    }

    public function list(Request $request)
    {
        // Ambil mahasiswa beserta relasi yang dibutuhkan untuk ditampilkan dan difilter
        $mahasiswaQuery = MahasiswaModel::with([
            'level',
            'prodi',
            'dpa', // Relasi ke DosenModel untuk DPA
            'dosenPembimbing', // Relasi ke DosenModel untuk Pembimbing Magang
            'magang' // Relasi ke MagangModel untuk cek status magang
        ])->select('m_mahasiswa.*'); // Selalu baik untuk select spesifik atau semua dari tabel utama

        if ($request->filled('level_id')) { // Jika ada filter level_id
            $mahasiswaQuery->where('level_id', $request->level_id);
        }
        // Tambahkan filter lain jika perlu

        return DataTables::of($mahasiswaQuery)
            ->addIndexColumn()
            ->addColumn('nama_lengkap_detail', function ($mahasiswa) {
                return view('admin_page.mahasiswa.partials.nama_detail', compact('mahasiswa'))->render();
            })
            ->addColumn('prodi_nama', fn($mahasiswa) => optional($mahasiswa->prodi)->nama_prodi ?? '-')
            ->addColumn('dpa_nama', fn($mahasiswa) => optional($mahasiswa->dpa)->nama_lengkap ?? '<span class="text-muted fst-italic">Belum Diatur</span>')
            ->addColumn('pembimbing_nama', fn($mahasiswa) => optional($mahasiswa->dosenPembimbing)->nama_lengkap ?? '<span class="text-muted fst-italic">Belum Ada</span>')
            ->addColumn('status_magang_display', function ($mahasiswa) {
                if ($mahasiswa->magang && in_array(strtolower($mahasiswa->magang->status), ['belum', 'sedang'])) {
                    return '<span class="badge bg-success">Sedang/Akan Magang</span>';
                } elseif ($mahasiswa->magang && strtolower($mahasiswa->magang->status) == 'selesai') {
                    return '<span class="badge bg-info">Magang Selesai</span>';
                }
                // Cek juga dari pengajuan jika belum masuk MagangModel tapi sudah diterima
                $pengajuanDiterima = $mahasiswa->pengajuan()
                                        ->where('status', 'diterima') // Sesuai ENUM PengajuanModel
                                        ->first();
                if($pengajuanDiterima && !$mahasiswa->magang){ // Diterima pengajuan tapi belum jadi magang record
                     return '<span class="badge bg-primary">Diterima (Menunggu Magang)</span>';
                }

                return '<span class="badge bg-secondary">Belum Magang</span>';
            })
            ->addColumn('aksi', function ($mahasiswa) {
                $btn = '<button onclick="modalAction(\'' . route('mahasiswa.verifikasi', $mahasiswa->mahasiswa_id) . '\')" class="btn btn-info btn-sm me-1 mb-1" title="Verifikasi Dokumen"><i class="fas fa-user-check"></i></button>';
                $btn .= '<button onclick="modalAction(\'' . route('mahasiswa.edit', $mahasiswa->mahasiswa_id) . '\')" class="btn btn-warning btn-sm me-1 mb-1" title="Edit Mahasiswa"><i class="fas fa-edit"></i></button>';
                $btn .= '<button onclick="modalAction(\'' . route('mahasiswa.deleteModal', $mahasiswa->mahasiswa_id) . '\')" class="btn btn-danger btn-sm mb-1" title="Hapus Mahasiswa"><i class="fas fa-trash"></i></button>';
                return $btn;
            })
            ->rawColumns(['aksi', 'dpa_nama', 'pembimbing_nama', 'status_magang_display', 'status_akun', 'nama_lengkap_detail'])
            ->make(true);
    }

    public function create(Request $request)
    {
        $prodi = ProdiModel::all();
        $level = LevelModel::all();
        $dosen = DosenModel::all();

        if ($request->ajax()) {
            return view('admin_page.mahasiswa.create', compact('prodi', 'level', 'dosen'));
        }

        $activeMenu = 'mahasiswa';
        return view('admin_page.mahasiswa.create', compact('prodi', 'level', 'dosen', 'activeMenu'));
    }

    public function store(Request $request)
    {
        if ($request->ajax()) {
            $rules = [
                'nama_lengkap' => 'required',
                'email'        => 'required|email|unique:m_mahasiswa,email',
                'password'     => 'required|min:6',
                'ipk'          => 'nullable|numeric|min:0|max:4',
                'nim'          => 'required|unique:m_mahasiswa,nim',
                'status'       => 'required|boolean',
                'level_id'     => 'required',
                'prodi_id'     => 'required',
                'dosen_id'     => 'nullable',
                'dpa_id'       => 'nullable'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status'   => false,
                    'message'  => 'Validasi Gagal',
                    'msgField' => $validator->errors(),
                ]);
            }

            MahasiswaModel::create([
                'nama_lengkap' => $request->nama_lengkap,
                'email'        => $request->email,
                'password'     => bcrypt($request->password),
                'ipk'          => $request->ipk,
                'nim'          => $request->nim,
                'status'       => $request->status,
                'level_id'     => $request->level_id,
                'prodi_id'     => $request->prodi_id,
                'dosen_id'     => $request->dosen_id,
                'dosen_id'     => $request->dpa_id,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Mahasiswa berhasil ditambahkan',
            ]);
        }

        return redirect('/');
    }

    public function show(Request $request, $id)
    {
        $mahasiswa = MahasiswaModel::with(['prodi', 'level', 'dosen', 'preferensiLokasi', 'skills'])->find($id);

        if ($request->ajax()) {
            return view('admin_page.mahasiswa.show', compact('mahasiswa'));
        }

        return redirect('/');
    }

    public function edit(Request $request, $id) // $id adalah mahasiswa_id
    {
        $mahasiswa = MahasiswaModel::with(['prodi', 'level', 'dpa', 'dosenPembimbing', 'magang'])->findOrFail($id);
        $prodiList = ProdiModel::orderBy('nama_prodi')->get(); // Ganti nama variabel agar tidak bentrok
        $levelList = LevelModel::whereIn('level_nama', ['Mahasiswa', 'MHS'])->get(); // Hanya level mahasiswa

        // Ambil dosen yang bisa jadi DPA (misal semua dosen atau dosen dengan role 'dpa')
        $dosenDpaList = DosenModel::where('role_dosen', 'dpa')->orderBy('nama_lengkap')->get();
        if($dosenDpaList->isEmpty()){ // Fallback jika tidak ada DPA spesifik
            $dosenDpaList = DosenModel::orderBy('nama_lengkap')->get();
        }

        // Ambil dosen yang bisa jadi Pembimbing (misal dosen dengan role 'pembimbing')
        $dosenPembimbingList = DosenModel::where('role_dosen', 'pembimbing')->orderBy('nama_lengkap')->get();
         if($dosenPembimbingList->isEmpty()){ // Fallback jika tidak ada pembimbing spesifik
            $dosenPembimbingList = DosenModel::orderBy('nama_lengkap')->get();
        }

        // Cek status magang mahasiswa
        $statusMagangMahasiswa = null;
        if ($mahasiswa->magang) {
            $statusMagangMahasiswa = $mahasiswa->magang->status; // e.g., 'belum', 'sedang', 'selesai'
        } else {
            // Cek juga dari tabel pengajuan jika statusnya 'diterima' tapi belum masuk magangModel
            $pengajuanDiterima = $mahasiswa->pengajuan()->where('status', 'diterima')->first();
            if ($pengajuanDiterima) {
                $statusMagangMahasiswa = 'akan_magang'; // Status custom untuk menandakan sudah diterima tapi belum di magangModel
            }
        }


        if ($request->ajax()) {
            return view('admin_page.mahasiswa.edit', compact(
                'mahasiswa',
                'prodiList',
                'levelList',
                'dosenDpaList',
                'dosenPembimbingList',
                'statusMagangMahasiswa'
            ));
        }

        // Untuk non-AJAX (jika ada), meskipun biasanya modal edit via AJAX
        $activeMenu = 'mahasiswa';
        return view('admin_page.mahasiswa.edit', compact(
            'mahasiswa',
            'prodiList',
            'levelList',
            'dosenDpaList',
            'dosenPembimbingList',
            'statusMagangMahasiswa',
            'activeMenu'
        ));
    }

     public function update(Request $request, $id)
    {
        // Logika update Anda yang sudah ada sebelumnya
        // Tambahkan validasi dan penyimpanan untuk dpa_id dan dosen_id (pembimbing)

        $mahasiswa = MahasiswaModel::findOrFail($id);

        $rules = [
            'nama_lengkap' => 'required|string|max:255',
            'email'        => 'required|email|unique:m_mahasiswa,email,' . $id . ',mahasiswa_id',
            'telepon'      => 'required|min:9|max:15',
            'nim'          => 'required|string|max:15|unique:m_mahasiswa,nim,' . $id . ',mahasiswa_id',
            'ipk'          => 'nullable|numeric|min:0|max:4.00',
            'status'       => 'required|boolean', // Status akun mahasiswa (aktif/tidak)
            'level_id'     => 'required|exists:m_level_user,level_id',
            'prodi_id'     => 'required|exists:tabel_prodi,prodi_id', // Pastikan nama tabel prodi benar
            'foto'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
            'password'     => 'nullable|string|min:6|max:20',

            'dpa_id'       => 'nullable|exists:m_dosen,dosen_id', // DPA bisa dipilih dari semua dosen
            'dosen_id'     => 'nullable|exists:m_dosen,dosen_id', // Dosen Pembimbing bisa dipilih
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'   => false,
                'message'  => 'Validasi gagal.',
                'msgField' => $validator->errors()->toArray()
            ], 422);
        }

        $dataToUpdate = $request->only([
            'nama_lengkap', 'email', 'telepon', 'nim', 'ipk', 'status', 'level_id', 'prodi_id',
            'dpa_id', // Simpan DPA ID
            'dosen_id' // Simpan Dosen Pembimbing ID (sebelumnya juga dosen_id)
        ]);

        if ($request->filled('password')) {
            $dataToUpdate['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('foto')) {
            // Hapus foto lama jika ada
            if ($mahasiswa->foto && Storage::disk('public')->exists('mahasiswa/foto/' . $mahasiswa->foto)) {
                Storage::disk('public')->delete('mahasiswa/foto/' . $mahasiswa->foto);
            }
            // Simpan foto baru
            $namaFileFoto = time() . '_' . $request->file('foto')->getClientOriginalName();
            $request->file('foto')->storeAs('mahasiswa/foto', $namaFileFoto, 'public');
            $dataToUpdate['foto'] = $namaFileFoto;
        }

        $mahasiswa->update($dataToUpdate);

        return response()->json([
            'status'  => true,
            'message' => 'Data mahasiswa berhasil diperbarui.'
        ]);
    }

    public function deleteModal(Request $request, $id)
    {
        $mahasiswa = MahasiswaModel::with(['prodi', 'level', 'dosen'])->find($id);
        return view('admin_page.mahasiswa.delete', compact('mahasiswa'));
    }

    public function delete_ajax(Request $request, $id)
    {
        if (! $request->ajax()) {
            return redirect()->route('mahasiswa.index');
        }

        $mahasiswa = MahasiswaModel::find($id);
        if ($mahasiswa) {
            $mahasiswa->delete();
            return response()->json([
                'status'  => true,
                'message' => 'Mahasiswa berhasil dihapus',
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Data mahasiswa tidak ditemukan',
        ]);
    }
    public function verifikasi(Request $request, $id)
    {
        $mahasiswa = MahasiswaModel::with(['prodi', 'dpa', 'dosenPembimbing']) // Eager load relasi yang mungkin ditampilkan
                                   ->findOrFail($id);
        // Data prodi, level, dosen untuk dropdown di form edit, mungkin tidak semua relevan untuk modal verifikasi
        // tapi tidak masalah jika dikirim.
        // $prodiList = ProdiModel::all(); // Ganti nama variabel agar tidak bentrok
        // $levelList = LevelModel::all();
        // $dosenList = DosenModel::all();

        if ($request->ajax()) {
            // Untuk modal, kita hanya perlu $mahasiswa
            return view('admin_page.mahasiswa.verifikasi', compact('mahasiswa'));
        }

        // Untuk halaman penuh (jika ada)
        $activeMenu = 'mahasiswa';
        // return view('admin_page.mahasiswa.verifikasi', compact('mahasiswa', 'prodiList', 'levelList', 'dosenList', 'activeMenu'));
        // Karena ini modal, baris di atas mungkin tidak akan pernah tereksekusi jika selalu AJAX
        // Jika Anda punya halaman verifikasi non-modal, sesuaikan variabel yang di-pass.
        // Untuk modal, cukup:
        return view('admin_page.mahasiswa.verifikasi', compact('mahasiswa'));
    }
   public function updateVerifikasi(Request $request, $id)
    {
        $mahasiswa = MahasiswaModel::findOrFail($id);

        $rules = [
            'status_verifikasi' => 'required|string|in:pending,valid,invalid',
            'alasan'            => 'required_if:status_verifikasi,invalid|nullable|string|max:1000',
            'skor_ais'          => 'nullable|integer|min:0|max:1000', // Sesuaikan max jika perlu
            'kasus'             => 'required|string|in:ada,tidak_ada',
            // Mahasiswa yang input organisasi dan lomba, admin hanya verifikasi profil secara umum
            // 'organisasi'        => 'required|string|in:aktif,sangat_aktif,tidak_ikut',
            // 'lomba'             => 'required|string|in:aktif,sangat_aktif,tidak_ikut',
        ];

        $messages = [
            'status_verifikasi.required' => 'Status verifikasi wajib dipilih.',
            'status_verifikasi.in'       => 'Status verifikasi tidak valid.',
            'alasan.required_if'         => 'Alasan penolakan wajib diisi jika status verifikasi adalah Invalid.',
            'skor_ais.integer'           => 'Skor AIS harus berupa angka.',
            'skor_ais.min'               => 'Skor AIS minimal 0.',
            'kasus.required'             => 'Status kasus mahasiswa wajib dipilih.',
            'kasus.in'                   => 'Status kasus tidak valid.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $mahasiswa->status_verifikasi = $request->status_verifikasi;
            $mahasiswa->alasan            = $request->status_verifikasi === 'invalid' ? $request->alasan : null;
            $mahasiswa->skor_ais          = $request->input('skor_ais', $mahasiswa->skor_ais); // Jika tidak diisi, jangan ubah skor_ais yg ada
            $mahasiswa->kasus             = $request->kasus;

            // Kolom organisasi dan lomba diisi oleh mahasiswa, jadi tidak diupdate di sini oleh admin
            // kecuali jika memang ada kebutuhan admin untuk meng-override.
            // Jika admin bisa override, tambahkan ke $request->only() dan $fillable di model.
            // $mahasiswa->organisasi = $request->organisasi;
            // $mahasiswa->lomba = $request->lomba;

            $mahasiswa->save();

            return response()->json(['success' => true, 'message' => 'Status verifikasi dan data mahasiswa berhasil diperbarui.']);

        } catch (\Exception $e) {
            Log::error("Error updating verifikasi mahasiswa ID {$id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }
}
