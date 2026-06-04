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
    <aside className="w-[220px] min-h-screen bg-[#1a2e1a] flex flex-col shrink-0">
      <div className="px-6 py-6">
        <p className="text-white font-semibold text-sm tracking-wide">Rwanda FDA</p>
        <p className="text-green-300 text-xs mt-0.5">Regulatory Authority</p>
      </div>
      <Separator className="bg-green-900" />
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label }) => {
          const active = pathname.startsWith(href)
          return (
            <Link
              key={href}
              href={href}
              className={`flex items-center px-3 py-2 rounded text-sm transition-colors ${
                active
                  ? 'bg-[#2d6a2d] text-white border-l-2 border-green-300'
                  : 'text-green-200 hover:bg-[#2d6a2d] hover:text-white'
              }`}
            >
              {label}
            </Link>
          )
        })}
      </nav>
      <div className="px-6 py-4">
        <p className="text-green-700 text-xs">Post-Market Surveillance</p>
      </div>
    </aside>
  )
}
