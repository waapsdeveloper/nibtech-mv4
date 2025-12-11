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
        gap: 1rem;
        margin-top: 1rem;
    }

    .support-panel {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        padding: 1rem;
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
        margin-top: 0.75rem;
        padding: 0.5rem;
        border-radius: 0.75rem;
        background: #f6f7fb;
    }

    .message {
        background: #fff;
        border-radius: 0.75rem;
        padding: 0.6rem 0.75rem;
        margin-bottom: 0.6rem;
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

    .email-links {
        background: #fff;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        padding: 0.6rem 0.85rem;
    }

    .email-links ul {
        list-style: disc;
        color: #1f2937;
    }

    .email-links a {
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
    }

    .email-links a:hover {
        text-decoration: underline;
    }

    .support-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.4rem;
        margin-top: 0.5rem;
    }

    .meta-pill {
        background: #eef2ff;
        border-radius: 0.5rem;
        padding: 0.35rem 0.5rem;
        font-size: 0.8rem;
    }

    .support-sidebar-section {
        margin-bottom: 0.75rem;
    }

    .support-sidebar-section:last-child {
        margin-bottom: 0;
    }

    .support-reply-panel {
        border: 1px solid #e0e7ff;
        border-radius: 0.75rem;
        padding: 0.6rem 0.75rem;
        background: #fdfdff;
    }

    .support-reply-panel textarea {
        resize: vertical;
    }

    .support-order-panel {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
        background: #f8fafc;
    }

    .support-order-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.65rem;
    }

    .support-order-panel .table {
        border-color: #e5e7eb;
    }

    .support-order-payload {
        border: 1px solid #cbd5f5;
        border-radius: 0.75rem;
        background: #eef2ff;
        padding: 0.75rem 1rem;
    }

    .support-order-payload pre {
        background: #1e293b;
        color: #f8fafc;
        border-radius: 0.5rem;
        padding: 0.75rem;
        font-size: 0.8rem;
        max-height: 220px;
        overflow: auto;
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
