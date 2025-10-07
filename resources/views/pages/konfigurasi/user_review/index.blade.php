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
                                    $hue = ($rating->rating / 10) * 120; // 0=merah, 120=hijau
                                    $color = "hsl({$hue}, 80%, 45%)";
                                @endphp

                                <tr>
                                    <td>{{ $ratings->firstItem() + $index }}</td>
                                    <td>{{ $rating->user->name ?? 'Tidak diketahui' }}</td>
                                    <td>
                                        <span class="badge text-white" style="background-color: {{ $color }}">
                                            {{ $rating->rating }}
                                        </span>
                                    </td>
                                    <td>{{ $rating->description }}</td>
                                    <td>
                                        @foreach ($rating->moods as $mood)
                                            <span class="badge bg-info text-white">{{ $mood->name }}</span>
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
