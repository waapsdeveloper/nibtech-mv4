@extends('layouts.app')

@php
    session()->put('page_title', 'Support Tickets');
@endphp

@section('styles')
<style>
    .support-shell {
        min-height: calc(100vh - 140px);
        background: linear-gradient(135deg, #f5f7fb 0%, #f0f4ff 40%, #f8fbff 100%);
        padding: 1.5rem;
        border-radius: 1.25rem;
    }

    .support-grid {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .support-panel {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .support-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }

    .support-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .support-thread {
        border: 1px solid transparent;
        border-radius: 0.9rem;
        padding: 0.85rem 1rem;
        text-align: left;
        background: #f9fbff;
        transition: border-color 0.2s ease, background 0.2s ease;
    }

    .support-thread.active {
        border-color: #2563eb;
        background: #e8efff;
    }

    .tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.65rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(37, 99, 235, 0.08);
    }

    .tag-chip::before {
        content: '';
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: currentColor;
    }

    .support-detail-header {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        align-items: center;
    }

    .message-feed {
        margin-top: 1.25rem;
        padding: 1rem;
        border-radius: 1rem;
        background: #f6f7fb;
        overflow-y: auto;
        flex: 1;
    }

    .message {
        background: #fff;
        border-radius: 1rem;
        padding: 0.85rem 1rem;
        margin-bottom: 0.85rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
    }

    .message.outbound {
        border-left: 4px solid #16a34a;
    }

    .message.inbound {
        border-left: 4px solid #2563eb;
    }

    .message-note {
        border-left: 4px solid #f97316;
        background: #fff7ed;
    }

    .translation-controls button {
        white-space: nowrap;
    }

    .message-translation {
        background: #eef2ff;
        border-radius: 0.75rem;
        border: 1px dashed #c7d2fe;
        padding: 0.65rem 0.85rem;
        font-size: 0.9rem;
        color: #1e1b4b;
    }

    .message-translation .translation-label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #4338ca;
    }

    .support-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .meta-pill {
        background: #eef2ff;
        border-radius: 0.75rem;
        padding: 0.65rem 0.85rem;
        font-size: 0.9rem;
    }

    @media (max-width: 1100px) {
        .support-grid {
            grid-template-columns: 1fr;
        }

        .support-shell {
            padding: 1rem;
        }
    }
</style>
@endsection

@section('content')
    <livewire:support-tickets />
@endsection
