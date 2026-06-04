// components/Sidebar.tsx
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Separator } from '@/components/ui/separator'

const links = [
  { href: '/recalls', label: 'Recalls' },
  { href: '/batches', label: 'Batches' },
  { href: '/ai', label: 'AI Query' },
]

export default function Sidebar() {
  const pathname = usePathname()

  return (
    <aside className="w-[220px] min-h-screen bg-[#1a1e2e] flex flex-col shrink-0">
      <div className="px-6 py-6">
        <p className="text-white font-semibold text-sm tracking-wide">NAFDAC</p>
        <p className="text-indigo-300 text-xs mt-0.5">Regulatory Authority</p>
      </div>
      <Separator className="bg-indigo-900" />
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label }) => {
          const active = pathname.startsWith(href)
          return (
            <Link
              key={href}
              href={href}
              className={`flex items-center px-3 py-2 rounded text-sm transition-colors ${
                active
                  ? 'bg-[#2d3a6a] text-white border-l-2 border-indigo-300'
                  : 'text-indigo-200 hover:bg-[#2d3a6a] hover:text-white'
              }`}
            >
              {label}
            </Link>
          )
        })}
      </nav>
      <div className="px-6 py-4">
        <p className="text-indigo-700 text-xs">Post-Market Surveillance</p>
      </div>
    </aside>
  )
}
