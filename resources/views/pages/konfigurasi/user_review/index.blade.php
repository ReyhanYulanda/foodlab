@extends('layouts.app')

@section('title', 'User Review Monitoring')

@section('content')
    <div class="container">
        <h1 class="mb-4">📊 Monitoring User Review</h1>

        <form method="GET" action="{{ route('user-review.index') }}" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                    placeholder="Cari user atau deskripsi...">
                <button class="btn btn-primary">Cari</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Rating</th>
                            <th>Deskripsi</th>
                            <th>Moods</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ratings as $index => $rating)
                            <tr>
                                <td>{{ $ratings->firstItem() + $index }}</td>
                                <td>{{ $rating->user->name ?? 'Unknown' }}</td>
                                <td><span class="badge bg-success">{{ $rating->rating }}</span></td>
                                <td>{{ $rating->description }}</td>
                                <td>
                                    @foreach ($rating->moods as $mood)
                                        <span class="badge bg-info text-dark">{{ $mood->name }}</span>
                                    @endforeach
                                </td>
                                <td>{{ $rating->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Belum ada rating</td>
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
@endsection