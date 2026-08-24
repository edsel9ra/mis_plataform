'use client';

import { useTranslations } from 'next-intl';

export interface OceanRadarPoint {
  key: string;
  trait: string;
  score: number;
}

interface OceanRadarChartProps {
  data: OceanRadarPoint[];
}

const GRID_LEVELS = [20, 40, 60, 80, 100];
const VIEW_BOX = 260;
const CENTER = VIEW_BOX / 2;
const RADIUS = 90;

export function OceanRadarChart({ data }: OceanRadarChartProps) {
  const t = useTranslations();

  if (data.length === 0) {
    return (
      <div className="flex h-full min-h-80 items-center justify-center rounded-2xl bg-white/60 text-sm text-slate-500">
        {t('personality.ocean_chart_empty')}
      </div>
    );
  }

  const axisPoints = data.map((_, index) => pointFor(index, data.length, RADIUS));
  const radarPoints = data.map((item, index) => pointFor(index, data.length, RADIUS * (Math.max(0, Math.min(100, item.score)) / 100)));
  const radarPath = radarPoints.map(({ x, y }) => `${x},${y}`).join(' ');

  return (
    <svg className="h-full min-h-80 w-full" viewBox={`0 0 ${VIEW_BOX} ${VIEW_BOX}`} role="img" aria-label={t('personality.ocean_chart_label')}>
      <defs>
        <radialGradient id="oceanRadarFill" cx="50%" cy="50%" r="60%">
          <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.34" />
          <stop offset="100%" stopColor="#2563eb" stopOpacity="0.18" />
        </radialGradient>
      </defs>

      {GRID_LEVELS.map((level) => {
        const points = data.map((_, index) => pointFor(index, data.length, RADIUS * (level / 100)));

        return (
          <polygon
            key={level}
            points={points.map(({ x, y }) => `${x},${y}`).join(' ')}
            fill="none"
            stroke="#cbd5e1"
            strokeWidth={level === 100 ? 1.5 : 1}
          />
        );
      })}

      {axisPoints.map(({ x, y }, index) => (
        <line key={data[index].key} x1={CENTER} y1={CENTER} x2={x} y2={y} stroke="#e2e8f0" strokeWidth="1" />
      ))}

      <polygon points={radarPath} fill="url(#oceanRadarFill)" stroke="#2563eb" strokeWidth="3" strokeLinejoin="round" />

      {radarPoints.map(({ x, y }, index) => (
        <g key={data[index].key}>
          <circle cx={x} cy={y} r="4.5" fill="#ffffff" stroke="#2563eb" strokeWidth="3">
            <title>{`${data[index].trait}: ${Math.round(data[index].score)}`}</title>
          </circle>
        </g>
      ))}

      {axisPoints.map((point, index) => {
        const labelPoint = pointFor(index, data.length, RADIUS + 22);

        return (
          <g key={`${data[index].key}-label`}>
            <text x={labelPoint.x} y={labelPoint.y - 2} textAnchor="middle" className="fill-slate-700 text-sm font-bold">
              {data[index].key}
            </text>
            <text x={labelPoint.x} y={labelPoint.y + 12} textAnchor="middle" className="fill-slate-500 text-[9px]">
              {Math.round(data[index].score)}
            </text>
          </g>
        );
      })}
    </svg>
  );
}

function pointFor(index: number, total: number, radius: number): { x: number; y: number } {
  const angle = (Math.PI * 2 * index) / total - Math.PI / 2;

  return {
    x: CENTER + Math.cos(angle) * radius,
    y: CENTER + Math.sin(angle) * radius,
  };
}
