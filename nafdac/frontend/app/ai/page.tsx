// app/ai/page.tsx
'use client'
import { useState, useEffect, useRef } from 'react'
import { api, AiQueryResponse } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent } from '@/components/ui/card'
import CitationLink from '@/components/CitationLink'

interface Message {
  role: 'user' | 'ai'
  content: string
  sources?: { object_id: string; chunk_text: string }[]
}

export default function AiPage() {
  const [messages, setMessages] = useState<Message[]>([])
  const [question, setQuestion] = useState('')
  const [loading, setLoading] = useState(false)
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  async function handleSend() {
    const q = question.trim()
    if (!q || loading) return

    setMessages(prev => [...prev, { role: 'user', content: q }])
    setQuestion('')
    setLoading(true)

    try {
      const res: AiQueryResponse = await api.ai.query(q)
      setMessages(prev => [
        ...prev,
        { role: 'ai', content: res.answer, sources: res.sources },
      ])
    } catch {
      setMessages(prev => [
        ...prev,
        { role: 'ai', content: 'An error occurred. Please try again.' },
      ])
    } finally {
      setLoading(false)
    }
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  return (
    <div className="flex flex-col h-full">
      {/* Page header */}
      <div className="mb-6 shrink-0">
        <h1 className="text-2xl font-semibold text-gray-900">AI Regulatory Query</h1>
        <p className="text-sm text-gray-500 mt-1">
          Ask questions about regulatory data across the shared ontology
        </p>
      </div>

      {/* Chat history */}
      <div className="flex-1 min-h-0 overflow-auto space-y-4 pb-4">
        {messages.length === 0 && !loading ? (
          <div className="flex flex-col items-center justify-center h-full text-center text-gray-400">
            <p className="text-base font-medium">Ask a regulatory question</p>
            <p className="text-sm mt-1">
              e.g. Are there any active recalls I should know about before approving a batch import?
            </p>
          </div>
        ) : (
          <>
            {messages.map((msg, i) =>
              msg.role === 'user' ? (
                <div key={i} className="flex justify-end">
                  <div
                    className="max-w-[70%] rounded-xl px-4 py-2.5 text-sm text-white"
                    style={{ backgroundColor: '#2d6a2d' }}
                  >
                    {msg.content}
                  </div>
                </div>
              ) : (
                <div key={i} className="flex justify-start">
                  <Card className="max-w-[80%]">
                    <CardContent className="text-sm leading-relaxed">
                      <CitationLink text={msg.content} />
                      {msg.sources && msg.sources.length > 0 && (
                        <details className="mt-3">
                          <summary className="cursor-pointer text-xs text-gray-500 hover:text-gray-700 select-none">
                            {msg.sources.length} source{msg.sources.length !== 1 ? 's' : ''}
                          </summary>
                          <div className="mt-2 space-y-2">
                            {msg.sources.map((src, j) => (
                              <div key={j} className="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                                <p className="text-xs font-mono text-gray-500 mb-1">{src.object_id}</p>
                                <p className="text-xs text-gray-600">{src.chunk_text}</p>
                              </div>
                            ))}
                          </div>
                        </details>
                      )}
                    </CardContent>
                  </Card>
                </div>
              )
            )}
            {loading && (
              <div className="flex justify-start">
                <Card className="max-w-[80%]">
                  <CardContent>
                    <p className="text-sm text-gray-400 animate-pulse">
                      Querying regulatory intelligence...
                    </p>
                  </CardContent>
                </Card>
              </div>
            )}
            <div ref={bottomRef} />
          </>
        )}
      </div>

      {/* Input bar */}
      <div className="shrink-0 border-t border-gray-200 pt-4 bg-[#f8f9fa] sticky bottom-0">
        <div className="flex gap-3 items-end">
          <Textarea
            value={question}
            onChange={e => setQuestion(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ask a regulatory question... (Enter to send, Shift+Enter for new line)"
            className="flex-1 resize-none min-h-[44px] max-h-40"
            disabled={loading}
          />
          <Button
            onClick={handleSend}
            disabled={loading || question.trim() === ''}
            size="lg"
          >
            {loading ? 'Querying...' : 'Send'}
          </Button>
        </div>
      </div>
    </div>
  )
}
