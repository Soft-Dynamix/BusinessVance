import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const projectId = searchParams.get('projectId');
    const entityType = searchParams.get('entityType');
    const limit = parseInt(searchParams.get('limit') || '50');

    const where: Record<string, unknown> = {};
    if (projectId) where.projectId = projectId;
    if (entityType) where.entityType = entityType;

    const logs = await db.activityLog.findMany({
      where,
      orderBy: { createdAt: 'desc' },
      take: Math.min(limit, 200),
    });

    return NextResponse.json({ logs });
  } catch (error) {
    console.error('Error fetching activity log:', error);
    return NextResponse.json({ error: 'Failed to fetch activity log' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { projectId, entityType, entityId, action, description, metadata, userId } = body;

    const log = await db.activityLog.create({
      data: {
        projectId: projectId || null,
        entityType: entityType || '',
        entityId: entityId || '',
        action: action || '',
        description: description || '',
        metadata: metadata ? JSON.stringify(metadata) : '{}',
        userId: userId || '',
      },
    });

    return NextResponse.json({ log }, { status: 201 });
  } catch (error) {
    console.error('Error creating activity log:', error);
    return NextResponse.json({ error: 'Failed to log activity' }, { status: 500 });
  }
}
