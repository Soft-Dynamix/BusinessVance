'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Save, RotateCcw } from 'lucide-react'

interface Settings {
  [key: string]: string
}

const SETTING_GROUPS = [
  {
    title: 'Branding',
    icon: '🏷️',
    keys: ['brand_name', 'brand_tagline', 'brand_description', 'header_icon', 'logo_url'],
  },
  {
    title: 'Theme Colors',
    icon: '🎨',
    keys: ['color_primary', 'color_primary_dark', 'color_accent', 'color_accent_dark', 'color_teal', 'color_orange'],
  },
  {
    title: 'Services Section',
    icon: '📋',
    keys: ['services_section_title', 'services_section_subtitle', 'services_section_cta'],
  },
  {
    title: 'Plans Section',
    icon: '💎',
    keys: ['plans_section_title', 'plans_section_subtitle', 'plans_section_cta'],
  },
  {
    title: 'Currency',
    icon: '💰',
    keys: ['currency_symbol', 'currency_position'],
  },
  {
    title: 'Footer',
    icon: '📌',
    keys: ['footer_company', 'footer_website', 'footer_phone', 'footer_email', 'footer_copyright'],
  },
  {
    title: 'Layout',
    icon: '📐',
    keys: ['layout_max_width', 'show_trust_badges', 'enable_animations', 'show_featured_badge'],
  },
]

const BOOLEAN_KEYS = ['show_trust_badges', 'enable_animations', 'show_featured_badge']

export function SettingsTab() {
  const [settings, setSettings] = useState<Settings>({})
  const [original, setOriginal] = useState<Settings>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  const fetchSettings = useCallback(async () => {
    try {
      const res = await fetch('/api/settings')
      if (res.ok) {
        const data = await res.json()
        setSettings(data.settings || {})
        setOriginal(data.settings || {})
      }
    } catch (e) { console.error(e) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { fetchSettings() }, [fetchSettings])

  const updateSetting = (key: string, value: string) => {
    setSettings(prev => ({ ...prev, [key]: value }))
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      const res = await fetch('/api/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ settings }),
      })
      if (res.ok) {
        setOriginal({ ...settings })
      }
    } catch (e) { console.error(e) }
    finally { setSaving(false) }
  }

  const handleReset = () => {
    setSettings({ ...original })
  }

  const hasChanges = JSON.stringify(settings) !== JSON.stringify(original)

  if (loading) return <div className="flex items-center justify-center py-12"><div className="animate-spin h-8 w-8 border-2 border-[#D4AF37] border-t-transparent rounded-full" /></div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-[#002B5C]">Plugin Settings</h2>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={handleReset} disabled={!hasChanges}>
            <RotateCcw className="w-4 h-4 mr-1" /> Reset
          </Button>
          <Button size="sm" onClick={handleSave} disabled={!hasChanges || saving}
            className="bg-[#D4AF37] hover:bg-[#c4a030] text-white">
            <Save className="w-4 h-4 mr-1" /> {saving ? 'Saving...' : 'Save Changes'}
          </Button>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {SETTING_GROUPS.map(group => (
          <Card key={group.title} className="border-gray-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-[#002B5C]">
                {group.icon} {group.title}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {group.keys.map(key => {
                const isBool = BOOLEAN_KEYS.includes(key)
                return (
                  <div key={key} className="space-y-1">
                    <Label className="text-xs text-gray-500 capitalize">
                      {key.replace(/_/g, ' ')}
                    </Label>
                    {isBool ? (
                      <Switch
                        checked={settings[key] === 'true' || settings[key] === '1'}
                        onCheckedChange={(checked) => updateSetting(key, checked ? 'true' : 'false')}
                      />
                    ) : key.includes('description') || key.includes('cta') ? (
                      <Textarea
                        value={settings[key] || ''}
                        onChange={e => updateSetting(key, e.target.value)}
                        className="text-sm"
                        rows={2}
                      />
                    ) : (
                      <Input
                        value={settings[key] || ''}
                        onChange={e => updateSetting(key, e.target.value)}
                        className="text-sm"
                        type={key.includes('color') ? 'color' : key.includes('url') ? 'url' : 'text'}
                      />
                    )}
                  </div>
                )
              })}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
