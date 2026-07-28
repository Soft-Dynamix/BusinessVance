import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

export async function GET() {
  try {
    const icons = await db.icon.findMany({
      orderBy: [{ category: 'asc' }, { displayOrder: 'asc' }, { name: 'asc' }],
    });
    return NextResponse.json({ icons });
  } catch (error) {
    console.error('Error fetching icons:', error);
    return NextResponse.json({ error: 'Failed to fetch icons' }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { name, label, svgPath, category, displayOrder } = body;

    if (!name || !label || !svgPath) {
      return NextResponse.json({ error: 'Name, label, and svgPath are required' }, { status: 400 });
    }

    const icon = await db.icon.create({
      data: {
        name: name.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/(^-|-$)/g, ''),
        label,
        svgPath,
        category: category || 'general',
        displayOrder: parseInt(displayOrder) || 0,
      },
    });

    return NextResponse.json({ icon }, { status: 201 });
  } catch (error) {
    console.error('Error creating icon:', error);
    return NextResponse.json({ error: 'Failed to create icon' }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();
    const { id, ...data } = body;

    if (!id) {
      return NextResponse.json({ error: 'Icon ID is required' }, { status: 400 });
    }

    if (data.displayOrder !== undefined) {
      data.displayOrder = parseInt(String(data.displayOrder)) || 0;
    }

    const icon = await db.icon.update({
      where: { id },
      data,
    });

    return NextResponse.json({ icon });
  } catch (error) {
    console.error('Error updating icon:', error);
    return NextResponse.json({ error: 'Failed to update icon' }, { status: 500 });
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Icon ID is required' }, { status: 400 });
    }

    await db.icon.delete({ where: { id } });
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Error deleting icon:', error);
    return NextResponse.json({ error: 'Failed to delete icon' }, { status: 500 });
  }
}
