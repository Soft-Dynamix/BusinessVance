import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  try {
    const { id } = await params;
    const body = await request.json();
    const { templateId, fullName, ipAddress, userAgent } = body;

    if (!templateId || !fullName) {
      return NextResponse.json({ error: 'Template ID and full name are required' }, { status: 400 });
    }

    const agreement = await db.projectAgreement.create({
      data: {
        projectId: id,
        templateId,
        fullName,
        ipAddress: ipAddress || '',
        userAgent: userAgent || '',
      },
    });

    // Update project status
    await db.project.update({
      where: { id },
      data: { status: 'awaiting-questionnaire', progressPercent: 25 },
    });

    return NextResponse.json({ agreement }, { status: 201 });
  } catch (error) {
    console.error('Error signing agreement:', error);
    return NextResponse.json({ error: 'Failed to sign agreement' }, { status: 500 });
  }
}
