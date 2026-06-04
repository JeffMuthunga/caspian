import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import './globals.css'
import Sidebar from '@/components/Sidebar'

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  title: 'Rwanda FDA — Post-Market Surveillance',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={`${inter.className} flex h-screen bg-[#f8f9fa] overflow-hidden`}>
        <Sidebar />
        <main className="flex-1 overflow-auto p-8">
          {children}
        </main>
      </body>
    </html>
  )
}
