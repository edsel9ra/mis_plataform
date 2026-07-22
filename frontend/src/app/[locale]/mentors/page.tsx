'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';

const MOCK_MENTORS = [
  { id: '1', name: 'Ana García', title: 'Mentora en Liderazgo', rating: 4.8, specialties: ['Liderazgo', 'Inteligencia Emocional'], ocean: { O: 78, C: 85, E: 72, A: 80, N: 35 } },
  { id: '2', name: 'Carlos López', title: 'Mentor en Innovación', rating: 4.6, specialties: ['Innovación', 'Estrategia'], ocean: { O: 90, C: 75, E: 60, A: 70, N: 30 } },
  { id: '3', name: 'María Torres', title: 'Mentora en Bienestar', rating: 4.9, specialties: ['Bienestar', 'Mindfulness'], ocean: { O: 65, C: 80, E: 55, A: 92, N: 28 } },
];

export default function MentorsPage() {
  const t = useTranslations();
  const [search, setSearch] = useState('');

  const filtered = MOCK_MENTORS.filter((m) =>
    m.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.mentors')}</h1>

      <div className="mt-6">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t('common.search')}
          className="w-full max-w-md rounded-lg border border-gray-300 px-4 py-2 focus:border-primary-500 focus:outline-none"
        />
      </div>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((mentor) => (
          <Link
            key={mentor.id}
            href={`/mentors/${mentor.id}`}
            className="rounded-xl border bg-white p-6 shadow-sm transition hover:shadow-md"
          >
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700">
                {mentor.name.charAt(0)}
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">{mentor.name}</h3>
                <p className="text-sm text-gray-500">{mentor.title}</p>
              </div>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              {mentor.specialties.map((s) => (
                <span key={s} className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{s}</span>
              ))}
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
