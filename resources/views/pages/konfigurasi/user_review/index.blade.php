<x-master-layout>
    <div class="main-content">
        <div class="title">
            Monitor User Review
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Daftar User Review</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('user_review.index') }}" class="mb-4">
                        <div class="input-group">
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                placeholder="Cari user atau deskripsi...">
                            <button class="btn btn-primary">Cari</button>
                        </div>
                    </form>

                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama User</th>
                                <th>Rating</th>
                                <th>Deskripsi</th>
                                <th>Moods</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ratings as $index => $rating)
                                @php
                                    if ($rating->rating <= 3) {
                                        $ratingColor = 'bg-danger text-white'; // Merah untuk rating 1–3
                                    } elseif ($rating->rating <= 6) {
                                        $ratingColor = 'bg-warning text-dark'; // Kuning untuk rating 4–6
                                    } elseif ($rating->rating <= 8) {
                                        $ratingColor = 'bg-info text-dark'; // Biru muda untuk rating 7–8
                                    } else {
                                        $ratingColor = 'bg-success text-white'; // Hijau untuk rating 9–10
                                    }
                                @endphp

                                <tr>
                                    <td>{{ $ratings->firstItem() + $index }}</td>
                                    <td>{{ $rating->user->name ?? 'Tidak diketahui' }}</td>
                                    <td>
                                        <span class="badge {{ $ratingColor }}">
                                            {{ $rating->rating }}
                                        </span>
                                    </td>
                                    <td>{{ $rating->description }}</td>
                                    <td>
                                        @foreach ($rating->moods as $mood)
                                            <span class="badge bg-info text-dark">{{ $mood->name }}</span>
                                        @endforeach
                                    </td>
                                    <td>{{ $rating->created_at->format('d-m-Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada rating yang dicatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $ratings->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
