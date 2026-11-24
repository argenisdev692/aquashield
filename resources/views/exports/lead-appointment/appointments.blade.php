@extends('exports.pdf.layout')

@section('content')
    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; color: #7a8fa8; padding: 8mm">
                        No appointments match the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
